<?php
    
    class MailConfig {
        const SANDBOX = FALSE;
        const PROTOCOL = "smtp";
        const PORT = "587";
        const HOST = "smtp.mandrillapp.com";
        const USERNAME = "yigittezel@gmail.com"; 
        const PASSWORD = "nRc04-Z9tPR78h7s4TSZYQ";
        
        // Varsayılan gerekli alanlar
        const FROM = 'gonderen@mail.com'; // gozuken email adresi
        const SENDER = 'gonderen@mail.com'; // yanıtla dediğinde gidecek adres
    }