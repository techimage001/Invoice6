<?php
require_once dirname(__DIR__).'/app_private/bootstrap.php';
$hash=password_hash('password123',PASSWORD_DEFAULT);
if(!password_verify('password123',$hash)) throw new Exception('Password hash test failed');
[$items,$sub,$tax,$total]=calc_items(['Cleaning service',''],[2,1],[50,999],20);
if(count($items)!==1 || $sub!==100.0 || round($tax,2)!==20.0 || round($total,2)!==120.0) throw new Exception('Calculation test failed');
$t=rand_token(); if(strlen($t)!==40 || !ctype_xdigit($t)) throw new Exception('Token test failed');
$required=['login_attempts','password_resets'];
foreach($required as $table){if(strpos(file_get_contents(dirname(__DIR__).'/app_private/bootstrap.php'),'CREATE TABLE IF NOT EXISTS '.$table)===false)throw new Exception('Missing migration for '.$table);}
echo "PASS: password hashing, quote/invoice calculations, secure tokens\n";
