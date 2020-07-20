<?php
defined('WEBNAME') OR exit('No direct script access allowed');
session_start();
    $query_id = mysqli_query($koneksi, "SELECT RIGHT(idSurat,4) as kode FROM surat
                                        WHERE tipe='REG'
                                        ORDER BY idSurat DESC LIMIT 1");
    $count = mysqli_num_rows($query_id);
    if ($count <> 0) {
    $data_id = mysqli_fetch_assoc($query_id);
    $kode    = $data_id['kode']+1;
    } else {
    $kode = 1;
    }
    $waktu   = date("yms");
    $buat_id = str_pad($kode, 4, "0", STR_PAD_LEFT);
    $trackID = "REG$waktu$buat_id";

$query  = mysqli_query($koneksi, "SELECT max(noUrut) as maxUrut FROM surat WHERE tipe='REG'");
$data   = mysqli_fetch_array($query);
$numb   = $data['maxUrut'];
$kode   = $numb + 1;
$noUrut = sprintf("%03s", $kode);

$pagette        = str_replace(" ", "", $_POST['pagette']);
//$tembusan       = $_POST['tembusan'];
$idJenis        = $_POST['idJenis'];
$idKlas         = $_POST['idKlas'];
$tglSurat       = mysqli_real_escape_string($koneksi, trim($_POST['tglSurat']));
$tglTempo       = mysqli_real_escape_string($koneksi, trim($_POST['tglTempo']));
$idSifat        = $_POST['idSifat']; 
$koresponden    = strtoupper(mysqli_real_escape_string($koneksi, trim($_POST['koresponden_out'])));
$perihal        = ucwords(mysqli_real_escape_string($koneksi, trim($_POST['perihal'])));

//$nama_file      = $_FILES['edoc']['name'];
$created_user   = $_SESSION['loginID'];
$idUser         = $_POST['pemeriksa'];  
$string = str_replace(' ', '_', $_FILES['edoc']['name']);
$nama_file = preg_replace("/[^A-Za-z0-9\-\.']/", '', $string);
$cekPemeriksa=mysqli_query($koneksi, "SELECT a.nip,a.nama,b.nmJabatan 
                                      FROM users a 
                                      LEFT JOIN jabatan b ON a.idJabatan=b.idJabatan 
                                      WHERE a.idUser='$idUser'");
list($nip,$nama,$nmJabatan)=mysqli_fetch_array($cekPemeriksa);

if (!empty($nama_file)){
  $ukuran_file        = $_FILES['edoc']['size'];
  $tipe_file          = $_FILES['edoc']['type'];
  $tmp_file           = $_FILES['edoc']['tmp_name'];        
  $allowed_extensions = array('pdf','PDF');
  $path               = "dokumen/regulasi/normal/".$nama_file;
  $file               = explode(".", $nama_file);
  $extension          = array_pop($file);
                
  if(in_array($extension, $allowed_extensions)) {
 
   if($ukuran_file <= 50000000) {
    if(move_uploaded_file($tmp_file, $path)) {
            
     $insertSurat = mysqli_query($koneksi, "INSERT INTO surat (tipe,trackID,noUrut,koresponden,tglSurat,tglTempo,idKlas,idJenis,idSifat,perihal,fileSurat,created_user,created_date,status_surat,pagette) VALUES ('REG','$trackID','$noUrut','$koresponden','$tglSurat','$tglTempo','$idKlas','$idJenis','$idSifat','$perihal','$nama_file','".$_SESSION['loginID']."',NOW(),'normal','$pagette')"); 

     $idSurat = mysqli_insert_id($koneksi);
     //$inserttembusan = mysqli_query($koneksi, "INSERT INTO tembusan (idSurat,tglTembusan,tembusan_txt) VALUES ('$idSurat',NOW(),'$tembusan')");
     $insertfile = mysqli_query($koneksi,"INSERT INTO file (idSurat,file_path,file_nm,created_dttm) VALUES ('$idSurat','dokumen/regulasi/normal/','$nama_file',NOW())");
     $info = 'Entri surat dengan meminta pemeriksaan/persetujuan kepada <b>'.$nama.'</b> (<em>'.$nmJabatan.'</em>)';
     $insertRespon = mysqli_query($koneksi, "INSERT INTO response (idSurat,send_user_id,receive_user_id,response_txt,action,created_dttm) VALUES ('$idSurat','".$_SESSION['loginID']."','$idUser','$info','belum',NOW())");            

     if($insertSurat) {
      // NOTIF WA

$sifatsurat = mysqli_query($koneksi, "SELECT nmSifat FROM sifat WHERE idSifat = '$idSifat'");
list($nmSifat)=mysqli_fetch_array($sifatsurat);
$cekSenderUser = mysqli_query($koneksi, "SELECT b.nmJabatan,a.nama
                                         FROM users a 
                                         LEFT JOIN jabatan b ON a.idJabatan=b.idJabatan 
                                         WHERE a.idUser='".$created_user."'");
list($jabSender,$nmSender)=mysqli_fetch_array($cekSenderUser);

$cekReceivedUser = mysqli_query($koneksi, "SELECT b.nmJabatan,a.nama,a.email,a.telepon 
                                           FROM users a 
                                           LEFT JOIN jabatan b ON a.idJabatan=b.idJabatan 
                                           WHERE a.idUser='".$idUser."'");
list($jabReceived,$nmReceived,$email,$telepon)=mysqli_fetch_array($cekReceivedUser);

$sys_apk=mysqli_query($koneksi, "SELECT nama,apikeys,website FROM sys_config");
list($nmAplikasi,$apikeys,$site)=mysqli_fetch_array($sys_apk);

$my_apikey = "$apikeys"; 
$destination = "$telepon"; 
$message = "_Assalamu’alaikum_ ...
Yth. Bpk/Ibu:
*$nmReceived* ($jabReceived)

Anda diminta untuk proses pemeriksaan/persetujuan surat dengan keterangan sebagai berikut:

Sifat Surat : *$nmSifat*
Dari: *$nmSender* ($jabSender)
Tujuan/Kepada: *$koresponden*
Perihal: *$perihal*
Track ID: *$trackID*

Silahkan login untuk melihat detail surat: $site

Terima Kasih,
$nmAplikasi
Instalasi IT - RSUD Palembang BARI"; 
$api_url = "http://panel.apiwha.com/send_message.php"; 
$api_url .= "?apikey=". urlencode ($my_apikey); 
$api_url .= "&number=". urlencode ($destination); 
$api_url .= "&text=". urlencode ($message); 
$my_result_object = json_decode(file_get_contents($api_url, false));                

// NOTIF WA
       echo "<script>location.href='?p=surat-regulasi-proses&do=entri&entri=success'</script>";
     } else {
       echo "<script>location.href='?p=surat-regulasi-proses&do=entri&entri=failed'</script>";
     }     
  } else {
    // Kalu file gagal diupload, tampilke pesan gagal upload
    echo "<script>location.href='?p=surat-regulasi-proses&do=entri&entri=failed1'</script>";
  }
    } else {
      // Kalu ukuran file lebih dari bates, tampilke pesan gagal upload
      echo "<script>location.href='?p=surat-regulasi-proses&do=entri&entri=failed2'</script>";
    }
  } else {
    // Kalu tipe file yang diupload bukan PDF dll, tampilke pesan gagal upload
    echo "<script>location.href='?p=surat-regulasi-proses&do=entri&entri=failed3'</script>";
  }
} else{
  // Kalu belum upload file
  echo "<script>location.href='?p=surat-regulasi-proses&do=entri&entri=failed4'</script>";
}
?>