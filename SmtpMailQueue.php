<?php
    
    require "Mail.php";
    require "Mail/mime.php";
    require "config/mail.config.php";
    
    
    class LogSmtpTable {
        
        const TABLE = 'log_smtp';
        
        public $log_smtp_id;
        public $insert_date;
        public $insert_user;
        public $send_date;
        public $status;
        public $email_data = array();
        
        /**
         * Verilen diziyi serialize eder.
         * 
         * @param array $email_data
         * @return string Serialized string
         */
        public function serialized_email_data($email_data) {
            $this->email_data = json_encode($email_data, JSON_UNESCAPED_UNICODE); //serialize($email_data);
            
            return $this->email_data;
        }
        
        /**
         * Serialize olan stringi diziye çevirir.
         * 
         * @param string $email_data
         * @return array
         */
        public function unserialize_email_data($email_data) {
            return json_decode($email_data, true); //unserialize($email_data);
        }
    }
    
    /**
     * Email için kuyruk sınıfı.
     */
    class SmtpQueue extends LogSmtpTable {
        
        const PENDING = 1; // sıraya alındı bekliyor..
        const SENDED = 2; // gönderim başarılı bir şekilde yapıldı.
        const REFUSED = 3; // hata ile karşılaşıldı. gönderim yapılamadı.
        
        const HIGH_PRIORITY = 1;
        const MEDIUM_PRIORITY = 2;
        const LOW_PRIORITY = 3;
        
        private $db;
        
        public function __construct($ez) {
            $current_date = new DateTime('now');
            
            $this->insert_date = $current_date->format('Y-m-d');
            $this->insert_user = USER_ID;
            $this->db = $ez;
        }
        
        /**
         * Kuyruğa ekleme yapar.
         * email_data dizisi için from, sender, cc ve html_body isteğe bağlıdır.
         * Zorunlu değildir. Cc bilgisi girilirken e-postalar virgül ile ayrılmalıdır.
         * File eklemek için file keyi ile file dosyasının bulunduğu dizini belirten
         * tek boyutlu bir dizi giriniz. 
         * 
         * @param array $email_data ['from' => string, sender => string, 
         * to => string, cc=> string, subject => string, text_body => string, html_body => string, file => array]
         * @param int $priority HighPriority:1 MediumPriority:2 LowPriority:3
         * @return array
         */
        public function add_queue($email_data, $priority = self::LOW_PRIORITY) {
            $data = array(
                'insert_date' => $this->insert_date,
                'insert_user' => $this->insert_user,
                'status'      => self::PENDING,
                'email_data'  => $this->serialized_email_data($email_data),
                'priority'    => $priority
            );
            
            $this->db->query("INSERT INTO ".parent::TABLE." SET ".$this->db->get_set($data));
            
            if($this->db->rows_affected > 0){
                return $this->get_debug_message(0, 'E-posta başarılı bir şekilde kuyruğa eklendi.');
            } else {
                return $this->get_debug_message(1, 'E-posta kuyruğa eklenemedi!');
            }
        }
        
        /**
         * Kuyruğu çalıştırır ve mailleri gönderir. Verilen statüye göre kuyruğu
         * çalıştırır.
         * @param int $status 1: PENDING, 3: REFUSED
         * @return array
         */
        public function execute_queue($status = self::PENDING) {
            $queues = $this->get_queue($status);
            
            foreach($queues as $queue) {
                $dizi[] = $this->send_mail($queue->log_smtp_id, $queue->email_data);
            }
            
            return $dizi;
        }
        
        /**
         * Kuyruktaki işleri raporlar.
         * 
         * @return object
         */
        public function get_report_queue() {
            $query = $this->db->get_results("SELECT status, COUNT(status) as nums FROM ".parent::TABLE." GROUP BY status");
            
            foreach($query as $data) {
                $report[] = array(
                    'status' => $this->get_status_name($data->status),
                    'count'  => $data->nums
                );
            }
            
            return $report;
        }
        
        
        /**
         * Kuyruktaki bekleyen işleri statüslerine göre getirir.
         * 
         * @param int $status 1: PENDING, 3: REFUSED  
         * @return object
         */
        protected function get_queue($status) {
            $query = FALSE;
            
            if($status !== self::SENDED) {
                $query = $this->db->get_results("SELECT * FROM ".parent::TABLE." WHERE status = ".$status." ORDER BY priority ASC");
            }
            
            return $query;
        }
        

        /**
         * Email gönderir.
         * 
         * @param int $log_smtp_id
         * @param array $email_data
         * @return array
         */
        protected function send_mail($log_smtp_id, $email_data) {
            
            $data = $this->unserialize_email_data($email_data);
       
            if(!array_key_exists('from', $data)){ $data['from'] = MailConfig::FROM; }
            if(!array_key_exists('sender', $data)){ $data['sender'] = MailConfig::SENDER; }
            if(!array_key_exists('html_body', $data)) { $data['html_body'] = $data['text_body']; }
            if(!array_key_exists('cc', $data)) { $data['cc'] = ''; }
            
            $headers = array(
                'From'          => $data['from'],
                'Return-Path'   => $data['sender'],
                'Subject'       => $data['subject'],
                'Cc'            => $data['cc'],
                'Content-Type'  => 'text/html; charset=UTF-8'
            );

            $mime_params = array(
              'text_encoding' => '7bit',
              'text_charset'  => 'UTF-8',
              'html_charset'  => 'UTF-8',
              'head_charset'  => 'UTF-8'
            );       
            
            $mime = new Mail_mime();

            $mime->setTXTBody($data['text_body']);
            $mime->setHTMLBody($data['html_body']);
            
            if(array_key_exists('file', $data)) {
                foreach($data['file'] as $file) {
                    $mime->addAttachment($file, 'application/octet-stream');
                }
            }
            
            $body = $mime->get($mime_params);
            $header = $mime->headers($headers);

            $mail = Mail::factory('smtp', array(
                'host' => MailConfig::HOST,
                'username' => MailConfig::USERNAME,
                'password' => MailConfig::PASSWORD, 
                'auth' => 'PLAIN'
            ));
            
            $mail->send($data['to'], $header, $body);
            
            if (PEAR::isError($mail)) {
                // Mail Gönderimi Başarısız!!!
                $this->set_queue_status($log_smtp_id, self::REFUSED); 
                
                return $this->get_debug_message(1, 'Mail gönderimi başarısız!!!');
            } else {
                // Mail Gönderimi Başarılı.
                $this->set_queue_status($log_smtp_id, self::SENDED);
                
                return $this->get_debug_message(0, 'Mail başarılı bir şekilde gönderildi.');
            }            
        }
        
        /**
         * Kuyruktaki işin statüsünü değiştirir.
         * 
         * @param int $log_smtp_id
         * @param int $status
         * @return boolean
         */
        protected function set_queue_status($log_smtp_id, $status) {
            
            $data = array(
                'send_date' => $this->insert_date,
                'status' => $status
            );
            
            $this->db->query("UPDATE ".parent::TABLE." SET ".$this->db->get_set($data)." WHERE log_smtp_id =".$log_smtp_id);
            
            if($this->db->rows_affected > 0) {
                return TRUE;
            } else {
                return FALSE;
            }
        }

        /**
         * Statüs numarasını texte çevirir.
         * 
         * @param int $status_id
         * @return string
         */
        protected function get_status_name($status_id) {
            switch ($status_id) {
                case self::PENDING:
                    $text = 'Kuyrukta bekliyor.';
                    break;
                case self::REFUSED:
                    $text = 'Gönderim başarısız oldu.';
                    break;
                case self::SENDED:
                    $text = 'Başarılı.';
                    break;
            }
            
            return $text;
        }
        
        /**
         * Hata mesajını ekrana basar.
         * 
         * @param int $status
         * @param string $message
         * @return array
         */
        protected function get_debug_message($status, $message) {
            return array(
                'error' => $status,
                'message' => $message
            );
        }
        

        
    }
    
