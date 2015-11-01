<?php
header('Content-Type: text/html; charset=utf-8');
 error_reporting(E_ALL);
session_start();
init();
require_once(  $_SESSION['PATH_WORK'].$_SESSION['PATH_INC'].'/list.inc.php');
require_once ( $_SESSION['PATH_WORK'].$_SESSION['PATH_INC'].'/class.phpmailer.php');
function init(){
       session_unset();
       $_SESSION['GPG_ENCRYPT_INPUTFILE']="mail.txt";
       $_SESSION['GPG_ENCRYPT_OUTPUTFILE']="sendmail.asc";
       $_SESSION['GPG_DECRYPT_INPUTFILE']="mail.asc";
       $_SESSION['GPG_DECRYPT_OUTPUTFILE']="mail.txt";
       $_SESSION['GPG_DECRYPT_MESSAGE_OUTPUTFILE']="tmp.txt";
       $_SESSION['ACCEPT_ENCRYPTED_ONLY']=1;
       $_SESSION['ACCEPT_MEMBER_ONLY']=1;
       $_SESSION['PATH_WORK']= "./" ;
       $_SESSION['PATH_GPG']="/usr/bin/gpg";
       $_SESSION['PATH_GPG_HOMEDIR']="~/.gnupg"; 
       $_SESSION['PATH_LISTS']="lists";
       $_SESSION['PATH_TMP']="tmp";
       $_SESSION['PATH_INC']="inc";
       $_SESSION['DELETE_MAILS']= 1;
       $_SESSION['SUBJECT_PRAEFIX']= '[gpg]';
       $_SESSION['ACCEPT_GOOD_SIGNATUR_ONLY']=1;
       $_SESSION['GOOD_SIGNATUR_PROOF']="Korrekte Unterschrift";
      $_SESSION['SEND_ERRORREPORT']=1;
             $_SESSION['GOOD_PGP_PROOF']="verschlüsselt mit 4096-Bit RSA Schlüssel, ID 1C114D36";

       $_SESSION['ERROR_START']="\n\r Hi,\n\rDu hast an den verschlüsselten Mailverteiler geschrieben. Leider kam es beim Verarbeiten deiner Mail zu einem Fehler. \n\r";
       $_SESSION['ERROR_NOT_SEND']=" Die Nachricht wurde nicht versendet.";
       $_SESSION['ERROR_NO_SIGNATUR']=" Es wurde keine gültige Signatur gefunden. Bitte beim Versenden die Mail auch signieren.";
       $_SESSION['ERROR_SENDER_NOT_LISTED']=" Die Emailadresse von der Du geschrieben hast wurde nicht in der Liste gefunden. Evt benutzt Du mehr als eine Adresse und hast die verkehrte erwischt ?";
       $_SESSION['ERROR_NOT_ENCRYPTED']=" Deine Nachricht war nicht korrekt verschlüsselt.";
       $_SESSION['ENCRYPT_MAIL']=1;

       $config=file($_SESSION['PATH_WORK'].'/bumerang.conf');
       $config=array_filter($config, create_function('$a','return preg_match("#\S#", $a);')); 
       $config = array_merge($config);
       for($i=0;$i<count($config);$i++){
        $dummy=trim($config[$i]);
        if (strpos(" ".$dummy,";")==1 || strpos(" ".$dummy,"*")==1|| strpos(" ".$dummy,"#")==1|| strpos(" ".$dummy,"/")==1){
          unset($config[$i]);
        }
        else{
          $sessiondummy=explode("=", $dummy);
          if (trim($sessiondummy[0])!="" && trim($sessiondummy[1])!=""){
            $_SESSION[trim( strtoupper($sessiondummy[0]))]=trim($sessiondummy[1]);
          }
        }
       }

}
function getLists(){
       $dummy=$_SESSION['LISTS'];
       $dummy=str_replace(array(',','|',':'), ";", $dummy);
       $ret=explode(";",$dummy);
       $ret=array_filter($ret, create_function('$a','return preg_match("#\S#", $a);')); 
       return $ret;
}
function checkList($list){
       checkListMail($list);
}

function flag(){
  $f = fopen($_SESSION['PATH_WORK']."flag","w");

  fwrite($f,date("Y-m-d_h_i",time()));


 fclose($f);
}
 if (file_exists($_SESSION['PATH_WORK']."flag")){
  echo "\n\r Skript läuft bereits";
  die();
 }
 else{
       @exec ("echo off rm ".$_SESSION['PATH_WORK'].$_SESSION['PATH_TMP']."*.*");
       flag();
       $lists=getLists();
       foreach($lists as $list){

              init();
              checkList($list);
       }
       exec ("rm ".$_SESSION['PATH_WORK']."flag");
  }    
       session_destroy();
?>
