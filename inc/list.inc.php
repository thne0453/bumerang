<?
header('Content-Type: text/html; charset=utf-8');

function trim2pgp($inputfile){

$data=file($inputfile);
$found_pgpstart=false;
$found_pgpdata=false;
$found_pgpstop=false;
$ret=array();
$z=0;
foreach ($data as $d){
 if (!$found_pgpstart)
 {
  if (strpos(" ".$d, "-----BEGIN PGP MESSAGE-----")){
    $ret[$z]="-----BEGIN PGP MESSAGE-----";
    $z++;
    $found_pgpstart=true;
  }

 }
 if (($found_pgpstart)&&(!$found_pgpdata))
 {
  if ((trim($d)=="")||(strpos(" ".$d,"=20"))){
    $ret[$z]="\r\n";
    $z++;
    $found_pgpdata=true;
  }

 }

 if (($found_pgpstart)&&($found_pgpdata)&&(!$found_pgpstop))
 {
  if (strpos(" ".$d, "-----END PGP MESSAGE-----")){
    $ret[$z]="-----END PGP MESSAGE-----";
    $z++;
    $found_pgpstop=true;
  }
  else{
        $txt = trim(str_replace("=20","",$d));
        $txt = trim(str_replace("=3D","=",$txt)) ;    
        $txt = trim(str_replace("=C3=A4","ä",$txt)) ;    
        $txt = trim(str_replace("=C3=84","Ä",$txt)) ;    
        $txt = trim(str_replace("=C3=B6","ö",$txt)) ;    
        $txt = trim(str_replace("=C3=96","Ö",$txt)) ;    
        $txt = trim(str_replace("=C3=BC","ü",$txt)) ;    
        $txt = trim(str_replace("=C3=9C","Ü",$txt)) ;    
        $txt = trim(str_replace("=C3=9F","ß",$txt)) ;    



        $ret[$z]=$txt."\n\r";
    $z++;
      
  }

 }
}
$f = fopen($inputfile,"w");
foreach ($ret as $d){
 fwrite($f,$d);
}
fclose($f);

}

function list_init($list){
  $config=file($_SESSION['PATH_WORK'].$_SESSION['PATH_LISTS'].'/'.$list.'/list.conf');
   for($i=0;$i<count($config);$i++){
        $dummy=trim($config[$i]);
        if (strpos(" ".$dummy,";")==1 || strpos(" ".$dummy,"*")==1|| strpos(" ".$dummy,"#")==1|| strpos(" ".$dummy,"/")==1){
          $config[$i]=" ";
        }
        else{
          $sessiondummy=explode("=", $dummy);
          if (trim(@$sessiondummy[0])!="" && trim(@$sessiondummy[1])!=""){
            $_SESSION[trim( strtoupper($sessiondummy[0]))]=trim($sessiondummy[1]);
          }
        }
       }
  $config=array_filter($config, create_function('$a','return preg_match("#\S#", $a);')); 
  $config = array_merge($config);  
}

function sendmail($recipient,$from,$subject,$body){
$mail = new PHPMailer;

$mail->IsSMTP();                                      // Set mailer to use SMTP
$mail->Host = $_SESSION['SMTP_SERVER'];  // Specify main and backup server
$mail->SMTPAuth = true;                               // Enable SMTP authentication
$mail->CharSet = "UTF-8";
$mail->Username = $_SESSION['SMTP_LOGIN'];                            // SMTP username
$mail->Password = $_SESSION['SMTP_PASSWORD'];                           // SMTP password
$mail->SMTPSecure = $_SESSION['SMTP_SECURITY'];  
                          // Enable encryption, 'ssl' also accepted

$mail->From = $_SESSION['GPG_USER'];
$mail->FromName =   $_SESSION['GPG_USER'].", ".$from;
$mail->AddAddress($recipient);  // Add a recipient

if (isset($_SESSION['REPLY_USER']) && (trim($_SESSION['REPLY_USER'])!="")){
$mail->AddReplyTo($_SESSION['REPLY_USER']);

}
else{
$mail->AddReplyTo($_SESSION['GPG_USER']);
}




$mail->IsHTML(false);                                  // Set email format to HTML

$mail->Subject = $subject;

$mail->Body    =  $body;

if(!$mail->Send()) {
   echo "\n\r".$_SESSION['ERROR_NOT_SEND'] . $mail->ErrorInfo;
   exit;
}
$time=date("d.m.Y H:i");
echo "\n\r ".$time." Message has been sent from ".$from." to ".$recipient;

}
function decode_utf8($str) {
   preg_match_all("/=\?UTF-8\?B\?([^\?]+)\?=/i",$str, $arr);
        
   for ($i=0;$i<count($arr[1]);$i++){
     $str=ereg_replace(ereg_replace("\?","\?",
              $arr[0][$i]),base64_decode($arr[1][$i]),$str);
   }
        return $str;
}


function doMailStuff(){
$_SESSION['message']="";
  
$imap = imap_open("{".$_SESSION['IMAP_SERVER'].":".$_SESSION['IMAP_PORT'].$_SESSION['IMAP_OPTION']."}", $_SESSION['IMAP_LOGIN'], $_SESSION['IMAP_PASSWORD']);

if( $imap ) { 
$headers = imap_headers($imap);
if (!($headers == false)) {
     $num = imap_num_msg($imap); 
     if( $num >0 ) { 
          for($i=1;$i<=$num;$i++){
          $_SESSION['message']="";
          $sendmail=true;  
          $mail_body=trim(imap_body($imap, $i)); 
          $mail_header = imap_header($imap, $i);
          $subject=$mail_header->subject;
          $from=$mail_header->from;
          $from=$from[0]->mailbox."@".$from[0]->host;
          if($_SESSION['ACCEPT_MEMBER_ONLY']==1){

            $dummylist=" ;".$_SESSION['RECIPIENT_LIST'].";";
            if (strpos(strtolower($dummylist), ";".strtolower($from).";")===false){
                $time=date("d.m.Y H:i");
                $_SESSION['message'].="\n\r".$time." ".$_SESSION['ERROR_SENDER_NOT_LISTED'];
               $sendmail=false;
           }     
         }
         
          if ($sendmail){
          $f = fopen($_SESSION['PATH_WORK'].$_SESSION['PATH_TMP'].$_SESSION['GPG_DECRYPT_INPUTFILE'],"w");
            fwrite($f, convert2utf8( strip_tags( $mail_body)));
          fclose($f);
          trim2pgp($_SESSION['PATH_WORK'].$_SESSION['PATH_TMP'].$_SESSION['GPG_DECRYPT_INPUTFILE']);
          

          if ($_SESSION['ACCEPT_ENCRYPTED_ONLY']==1){
            gpgDecrypt();            
            $output=implode("",file($_SESSION['PATH_WORK'].$_SESSION['PATH_TMP'].$_SESSION['GPG_DECRYPT_MESSAGE_OUTPUTFILE']));
             if (!strpos(" ".$output,$_SESSION['GOOD_PGP_PROOF'] )){              
              $time=date("d.m.Y H:i");
             $_SESSION['message'].="\n\r".$time.$_SESSION['ERROR_NOT_ENCRYPTED'];
              $sendmail=false;
             }             
           
            if ($_SESSION['ACCEPT_GOOD_SIGNATUR_ONLY']==1){
              
             
             if (!strpos(" ".$output,$_SESSION['GOOD_SIGNATUR_PROOF'] )){              
              $time=date("d.m.Y H:i");
              $_SESSION['message'].=  "\n\r".$time." ".$_SESSION['ERROR_NO_SIGNATUR'];
              $sendmail=false;
             }             
           }


          }
          else{
          $f = fopen($_SESSION['PATH_WORK'].$_SESSION['PATH_TMP'].$_SESSION['GPG_DECRYPT_OUTPUTFILE'],"w");
            fwrite($f, $mail_body);            
            fclose($f);          
           }
          $dummy=$_SESSION['RECIPIENT_LIST'];
          $dummy=str_replace(array(',','|',':'), ";", $dummy);
          $recipient_list=explode(";",$dummy);
            exec("cp ".$_SESSION['PATH_WORK'].$_SESSION['PATH_TMP'].$_SESSION['GPG_DECRYPT_OUTPUTFILE']." ".$_SESSION['PATH_WORK'].$_SESSION['PATH_TMP'].$_SESSION['GPG_ENCRYPT_INPUTFILE'], $output);
          if ($sendmail) {

          foreach ( $recipient_list as $rec){
            $sendmail=true;
            if (trim($rec)!=""){
          if ($_SESSION['ENCRYPT_MAIL']==1){
                gpgEncrypt($rec);
              }
          else{
            exec("cp ".$_SESSION['PATH_WORK'].$_SESSION['PATH_TMP'].$_SESSION['GPG_DECRYPT_OUTPUTFILE']." ".$_SESSION['PATH_WORK'].$_SESSION['PATH_TMP'].$_SESSION['GPG_ENCRYPT_OUTPUTFILE'], $output);           
           }
            if ( file_exists($_SESSION['PATH_WORK'].$_SESSION['PATH_TMP'].$_SESSION['GPG_ENCRYPT_INPUTFILE'])){
                  $body_array=file($_SESSION['PATH_WORK'].$_SESSION['PATH_TMP'].$_SESSION['GPG_ENCRYPT_INPUTFILE']);
                  $body1=" ".implode('',$body_array);
                }
                else{                  
                  $sendmail=false;
                }
                if ( file_exists($_SESSION['PATH_WORK'].$_SESSION['PATH_TMP'].$_SESSION['GPG_ENCRYPT_OUTPUTFILE'])){
                  $body_array=file($_SESSION['PATH_WORK'].$_SESSION['PATH_TMP'].$_SESSION['GPG_ENCRYPT_OUTPUTFILE']);
                  $body=implode($body_array);
                }   
                else{                  
                  $sendmail=false;
                }
                if ((trim($body)=="")||(trim($body1)=="")) {
                  $sendmail=false;
                }

                 if ($sendmail) { 
                 
                  $time=date("d.m.Y H:i");
                  $body=$from." - ".$time." : \n\r".$body;
                  $subject=str_replace("***UNCHECKED***", "", $subject);
                  $subject=str_replace("RE: ".$_SESSION['SUBJECT_PRAEFIX'], "", $subject);
                  $subject=str_replace("AW: ".$_SESSION['SUBJECT_PRAEFIX'], "", $subject);
                  $subject=str_replace("Re: ".$_SESSION['SUBJECT_PRAEFIX'], "", $subject);
                  $subject=str_replace("Aw: ".$_SESSION['SUBJECT_PRAEFIX'], "", $subject);
                  
                  $subject=trim($subject);
                    sendmail($rec,$from, $_SESSION['SUBJECT_PRAEFIX']." ".$subject,convert2utf8($body));
                }
                }
                

              }
            } 
            else{
              
                  $time=date("d.m.Y H:i");
                  $_SESSION['message']= "\n\r".$time.$_SESSION['ERROR_NOT_SEND']."\n\r".$_SESSION['message'];
                }
          }
          if($_SESSION['DELETE_MAILS']==1){
            imap_delete($imap, $i);  
           }

         }
          
     } 
  }

}
imap_expunge($imap);
imap_close($imap);
if (trim($_SESSION['message'])!="")
  {echo $_SESSION['message'];}
if ( $_SESSION['SEND_ERRORREPORT']==1){
 if (trim($_SESSION['message'])!=""){
    echo $rec,$from, $_SESSION['SUBJECT_PRAEFIX']." Fehlerbericht: ".$subject,convert2utf8($_SESSION['message']);
                sendmail($from,$_SESSION['GPG_USER'], $_SESSION['SUBJECT_PRAEFIX']."Fehlerbericht",convert2utf8($_SESSION['ERROR_START'].$_SESSION['message']));
  

 }
 }
}
function convert2utf8($content) { 
    if(!mb_check_encoding($content, 'UTF-8') 
        OR !($content === mb_convert_encoding(mb_convert_encoding($content, 'UTF-32', 'UTF-8' ), 'UTF-8', 'UTF-32'))) { 

        $content = mb_convert_encoding($content, 'UTF-8'); 

    } 
    return $content; 
} 
function gpgEncrypt($recipient){
  @unlink($_SESSION['PATH_WORK'].$_SESSION['PATH_TMP'].$_SESSION['GPG_ENCRYPT_OUTPUTFILE']);
  $command=$_SESSION['PATH_GPG']."gpg --homedir ".$_SESSION['PATH_GPG_HOMEDIR']." --passphrase \"".$_SESSION['GPG_PASSPHRASE']."\"  --local-user ".$_SESSION['GPG_USER']." -a --batch --recipient ".$recipient." -o ".$_SESSION['PATH_WORK'].$_SESSION['PATH_TMP'].$_SESSION['GPG_ENCRYPT_OUTPUTFILE']." -s -e ".$_SESSION['PATH_WORK'].$_SESSION['PATH_TMP'].$_SESSION['GPG_ENCRYPT_INPUTFILE']; 
  try {
      exec($command);
  }
  catch (Exception $e) {
    echo 'Exception abgefangen: ',  $e->getMessage(), "\n";
}
}

function gpgDecrypt(){
     @unlink($_SESSION['PATH_WORK'].$_SESSION['PATH_TMP'].$_SESSION['GPG_DECRYPT_OUTPUTFILE']);
  $command=$_SESSION['PATH_GPG']."gpg --no-tty --homedir ".$_SESSION['PATH_GPG_HOMEDIR']." --passphrase \"".$_SESSION['GPG_PASSPHRASE']."\" -o ".$_SESSION['PATH_WORK'].$_SESSION['PATH_TMP'].$_SESSION['GPG_DECRYPT_OUTPUTFILE']." -d ".$_SESSION['PATH_WORK'].$_SESSION['PATH_TMP'].$_SESSION['GPG_DECRYPT_INPUTFILE']." > ".$_SESSION['PATH_WORK'].$_SESSION['PATH_TMP'].$_SESSION['GPG_DECRYPT_MESSAGE_OUTPUTFILE']."  2>&1";
  try {
   exec($command);
  }
  catch (Exception $e) {
    echo 'Exception abgefangen: ',  $e->getMessage(), "\n";
}
}
function checkListMail($list){
  list_init($list);
  doMailStuff();
  @exec ("rm ".$_SESSION['PATH_WORK'].$_SESSION['PATH_TMP']."* > /dev/null 2>&1");
}
?>
