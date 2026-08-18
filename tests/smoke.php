<?php
if(!in_array('sqlite',PDO::getAvailableDrivers(),true)){
    fwrite(STDERR,"SKIP: pdo_sqlite is not installed in this test environment. The host must enable PDO SQLite.\n");
    exit(0);
}
putenv('DB_PATH='.dirname(__DIR__).'/storage/test.sqlite');@unlink(dirname(__DIR__).'/storage/test.sqlite');
require_once dirname(__DIR__).'/app_private/bootstrap.php';
$db=db();
$hash=password_hash('password123',PASSWORD_DEFAULT);$db->prepare('INSERT INTO users(email,password_hash,business_name,trial_ends_at) VALUES(?,?,?,?)')->execute(['test@example.com',$hash,'Test Services',date('Y-m-d',strtotime('+14 days'))]);$uid=(int)$db->lastInsertId();$db->prepare('INSERT INTO settings(user_id,business_name,business_email) VALUES(?,?,?)')->execute([$uid,'Test Services','test@example.com']);$_SESSION['user']=['id'=>$uid,'email'=>'test@example.com','business_name'=>'Test Services'];
$db->prepare('INSERT INTO customers(user_id,name,email) VALUES(?,?,?)')->execute([$uid,'Jane Customer','jane@example.com']);$cid=(int)$db->lastInsertId();
[$items,$sub,$tax,$total]=calc_items(['Cleaning service'],[2],[50],20);if($sub!==100.0||round($tax,2)!==20.0||round($total,2)!==120.0)throw new Exception('Calculation failure');
$qnum=next_number('quote');if($qnum!=='Q-00001')throw new Exception('Quote number failure: '.$qnum);
$db->prepare('INSERT INTO quotes(user_id,customer_id,quote_number,status,issue_date,subtotal,tax_rate,tax,total,public_token,next_followup_at) VALUES(?,?,?,?,?,?,?,?,?,?,?)')->execute([$uid,$cid,$qnum,'sent',date('Y-m-d'),$sub,20,$tax,$total,rand_token(),date('Y-m-d')]);
$count=(int)$db->query('SELECT COUNT(*) FROM quotes')->fetchColumn();if($count!==1)throw new Exception('Quote insert failure');
echo "PASS: database migration, customer and quote flow\n";
@unlink(dirname(__DIR__).'/storage/test.sqlite');
