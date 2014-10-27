<?php
    
    class MailConfig {
        const SANDBOX = FALSE;
        const PROTOCOL = "smtp";
        const PORT = "587";
        const HOST = "smtp.example.com";
        const USERNAME = "john@doe.com"; 
        const PASSWORD = "";
        
        // default settings
        const FROM = 'sender@example.com'; // from 
        const SENDER = 'sender@example.com'; // reply address
    }