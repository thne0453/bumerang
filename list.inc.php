<?
header('Content-Type: text/html; charset=utf-8');
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
$mail->CharSet = 'utf-8';
$mail->Username = $_SESSION['SMTP_LOGIN'];                            // SMTP username
$mail->Password = $_SESSION['SMTP_PASSWORD'];                           // SMTP password
$mail->SMTPSecure = $_SESSION['SMTP_SECURITY'];                            // Enable encryption, 'ssl' also accepted

$mail->From = $_SESSION['GPG_USER'];
$mail->FromName =  $from;
$mail->AddAddress($recipient);  // Add a recipient
$mail->AddReplyTo($_SESSION['GPG_USER']);

$mail->IsHTML(false);                                  // Set email format to HTML

$mail->Subject = $subject;

$mail->Body    = $body;

if(!$mail->Send()) {
   echo "\n\rMessage could not be sent.";
   echo "\n\rMailer Error: " . $mail->ErrorInfo;
   exit;
}
$time=date("d.m.Y H:i");
echo "\n\r ".$time." Message has been sent to ".$recipient;

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
$message="";
  
$imap = imap_open("{".$_SESSION['IMAP_SERVER'].":".$_SESSION['IMAP_PORT'].$_SESSION['IMAP_OPTION']."}", $_SESSION['IMAP_LOGIN'], $_SESSION['IMAP_PASSWORD']);

if( $imap ) { 
$headers = imap_headers($imap);
if ($headers == false) {
   echo "Error in Headers \n";
} else {
     $num = imap_num_msg($imap); 
     if( $num >0 ) { 
          for($i=1;$i<=$num;$i++){
          $message="";
          $sendmail=true;  
          $mail_body=imap_body($imap, $i); 
          $mail_header = imap_header($imap, $i);
          $subject=$mail_header->subject;
          $from=$mail_header->from;
          $from=$from[0]->mailbox."@".$from[0]->host;
          if($_SESSION['ACCEPT_MEMBER_ONLY']==1){
            $dummylist=" ;".$_SESSION['RECIPIENT_LIST'].";";
            if (strpos(strtolower($dummylist), ";".strtolower($from).";")===false){
                $time=date("d.m.Y H:i");
                $message.="\n\r".$time." Absendeadresse ".$from." nicht in Empfangsliste";
               $sendmail=false;
           }     
         }
         
          if ($sendmail){
          $f = fopen($_SESSION['PATH_WORK'].$_SESSION['PATH_TMP'].$_SESSION['GPG_DECRYPT_INPUTFILE'],"w");
          	fwrite($f, convert2utf8($mail_body));
		      fclose($f);

          if ($_SESSION['ACCEPT_ENCRYPTED_ONLY']==1){
            gpgDecrypt();            
            $output=implode("",file($_SESSION['PATH_WORK'].$_SESSION['PATH_TMP'].$_SESSION['GPG_DECRYPT_MESSAGE_OUTPUTFILE']));
             if (!strpos(" ".$output,$_SESSION['GOOD_PGP_PROOF'] )){              
              $time=date("d.m.Y H:i");
              $message.="\n\r".$time." Nachricht unverschlüsselt geschickt";
              $sendmail=false;
             }             
           
            if ($_SESSION['ACCEPT_GOOD_SIGNATUR_ONLY']==1){
              
             echo $output;
             if (!strpos(" ".$output,$_SESSION['GOOD_SIGNATUR_PROOF'] )){              
              $time=date("d.m.Y H:i");
              $message.="\n\r".$time." Keine gültige Signatur gefunden.";
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
            if (trim($rec)!=""){
          if ($_SESSION['ENCRYPT_MAIL']==1){
                gpgEncrypt($rec);
              }
          else{
            exec("cp ".$_SESSION['PATH_WORK'].$_SESSION['PATH_TMP'].$_SESSION['GPG_DECRYPT_OUTPUTFILE']." ".$_SESSION['PATH_WORK'].$_SESSION['PATH_TMP'].$_SESSION['GPG_ENCRYPT_OUTPUTFILE'], $output);           
           }
                $body_array=file($_SESSION['PATH_WORK'].$_SESSION['PATH_TMP'].$_SESSION['GPG_ENCRYPT_INPUTFILE']);
                $body1=" ".implode('',$body_array);
                $body_array=file($_SESSION['PATH_WORK'].$_SESSION['PATH_TMP'].$_SESSION['GPG_ENCRYPT_OUTPUTFILE']);
                $body=implode($body_array);
                if ((trim($body)=="")||(trim($body1)=="")) {
                  $sendmail=false;
 	            }

                if ($sendmail) {                              
                  sendmail($rec,$from, $_SESSION['SUBJECT_PRAEFIX'].$subject,convert2utf8($body));
                 }
                }                
              }
            } 
            else{
                  $time=date("d.m.Y H:i");
                  $message.= "\n\r".$time." Mail wird nicht versendet";
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
if (trim($message)!="")
  {echo $message;}
if ( $_SESSION['SEND_ERRORREPORT']==1){
 if (trim($message)!=""){
  echo $rec,$from, $_SESSION['SUBJECT_PRAEFIX']."Fehlerbericht",convert2utf8($message);
                sendmail($from,$_SESSION['GPG_USER'], $_SESSION['SUBJECT_PRAEFIX']."Fehlerbericht",convert2utf8($message));
  

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
  exec($command);
}

function gpgDecrypt(){
	   @unlink($_SESSION['PATH_WORK'].$_SESSION['PATH_TMP'].$_SESSION['GPG_DECRYPT_OUTPUTFILE']);
	$command=$_SESSION['PATH_GPG']."gpg --no-tty --homedir ".$_SESSION['PATH_GPG_HOMEDIR']." --passphrase \"".$_SESSION['GPG_PASSPHRASE']."\" -o ".$_SESSION['PATH_WORK'].$_SESSION['PATH_TMP'].$_SESSION['GPG_DECRYPT_OUTPUTFILE']." -d ".$_SESSION['PATH_WORK'].$_SESSION['PATH_TMP'].$_SESSION['GPG_DECRYPT_INPUTFILE']." > ".$_SESSION['PATH_WORK'].$_SESSION['PATH_TMP'].$_SESSION['GPG_DECRYPT_MESSAGE_OUTPUTFILE']."  2>&1";
	exec($command);

}
function checkListMail($list){
  list_init($list);
  doMailStuff();
  @exec ("rm ".$_SESSION['PATH_WORK'].$_SESSION['PATH_TMP']."* > /dev/null 2>&1");
}
?>
