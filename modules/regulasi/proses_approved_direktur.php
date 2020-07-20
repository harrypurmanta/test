<?php
@date_default_timezone_set('Asia/Jakarta');
require_once('../../inc/koneksi.php');
require_once('../../inc/function.php');
//require_once('tcpdf/tcpdf_include.php');
require_once('../../library/tcpdf/tcpdf.php');
require_once('../../library/fpdi2/src/autoload.php');
require_once('../../inc/esign-cli.php');
include '../../library/phpqrcode/qrlib.php';



if (empty($_SESSION['loginID'])){
    echo "<meta http-equiv='refresh' content='0; url=../../logout.php'>";
} else {
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
    $waktu   = date("ym");
    $buat_id = str_pad($kode, 4, "0", STR_PAD_LEFT);
    $suffix = "_signed_$waktu$buat_id";

  $id     =$_POST['idRespon'];
  $pin    =$_POST["pin"];  
  $idUser =$_SESSION['loginID'];
  $ambil = mysqli_query($koneksi, "SELECT a.idSurat,b.tipe,b.trackID,c.kdKlas,c.nmKlas,d.kdJenis,d.nmJenis,b.idSifat,b.perihal,b.fileSurat,b.created_user,b.pagette,e.tembusan_txt FROM response a LEFT JOIN surat b ON a.idSurat=b.idSurat LEFT JOIN klasifikasi c ON b.idKlas=c.idKlas LEFT JOIN jenis d ON b.idJenis=d.idJenis LEFT JOIN tembusan e ON e.idSurat=b.idSurat WHERE a.idResponse='$id' AND b.tipe='REG'");

  if(mysqli_num_rows($ambil)>0){
    list($idSurat,$tipe,$trackID,$kdKlas,$nmKlas,$kdJenis,$nmJenis,$idSifat,$perihal,$fileSurat,$created_user,$pagette,$tembusan_txt)=mysqli_fetch_array($ambil);
   
    $pagettex = explode(',',$pagette);
    $countpage = count($pagettex);

    

    $ext = pathinfo($fileSurat, PATHINFO_EXTENSION);
    $filename = basename($fileSurat, '.' .$ext);
    $signedSurat = $filename."_signed_".$waktu.$buat_id;

    $cekReceivedUser = mysqli_query($koneksi, "SELECT a.idUser,a.nip,b.idJabatan,b.nmJabatan,a.nama,a.email,a.telepon 
                                               FROM users a 
                                               LEFT JOIN jabatan b ON a.idJabatan=b.idJabatan 
                                               WHERE a.idUser='$created_user'");
    list($idUsr,$nip,$idJabatan,$nmJabatan,$nama,$email,$telepon)=mysqli_fetch_array($cekReceivedUser);

    $dataDirektur = mysqli_query($koneksi, "SELECT a.nip,a.pin,b.nmJabatan,a.nama,a.email 
                                            FROM users a 
                                            LEFT JOIN jabatan b ON a.idJabatan=b.idJabatan 
                                            WHERE b.nmJabatan='Direktur'");
    list($nip_dir,$pin_dir,$jabatan_dir,$nama_dir,$email_dir)=mysqli_fetch_array($dataDirektur);
//error_log(print_r($filename,true));
   
if($pin!==''){
// $projectName = explode('/',$_SERVER['PHP_SELF'])[1];
$basePath = $_SERVER['DOCUMENT_ROOT']

$text = 'https://dosis.cliniccoding.id/dosis/dokumen/regulasi/signed/'.$filename.$suffix.'.pdf';
$path = $basePath.'/images/';
$file = $path.$trackID.".png";
$png = "/images/".$trackID.".png";
$ecc = 'H'; 
$pixel_size = 4; 
$frame_size = 1; 
QRcode::png($text, $file, $ecc, $pixel_size, $frame_size);


// PDF BSRE
$pathPDF = 'dokumen/regulasi/normal/'.$fileSurat;
$pdfSigner =  new BSrE_Esign_Cli();
$pdfSigner->copyTotmp($pathPDF,'dokumen/regulasi/tmp',$filename);
$filepdf = 'dokumen/regulasi/tmp/'.$fileSurat;
for ($i=0; $i< $countpage; $i++) {  

    if ($i<$countpage) {
      $split = preg_split("/(,?\s+)|((?<=[a-z])(?=\d))|((?<=\d)(?=[a-z]))/i", $pagettex[$i]);
      if ($split[0]=='L'||$split[0]=='l') { // JIKA LANDSCAPE
        $tte = '/modules/icon/label_tt_dir_lanscape.png';
        $pagex = $split[1];
        $xx = 230;
        $yy = 1;
        $widthx = 770;
        $heightx = 170;

      } else if ($split[0]=='S'||$split[0]=='s') { // JIKA SOP

        $tte = '/modules/icon/label_tte_sop_gabung_tanpa_barcode.png';
        $pagex = $split[1];
        $xx = 100;
        $yy = 10;
        $widthx = 535;
        $heightx = 710;

        $pdfSigner->setAppearance(
          $x = 410,
          $y = 750,
          $width = 479,
          $height = 479,
          $page = $pagex,
          $spesimen = $png,
          $qr = ''
        );
        //error_log(print_r($file,true));
        $pdfSigner->setDocument($filepdf);
        $hasil = $pdfSigner->sign(
          '1671065304650006',   //nik
          $_POST["pin"]    //passphrase
        );

      } else {
        $tte = '/modules/icon/label_tt_dir.png';
        $pagex = $split[0];
        $xx = -300;
        $yy = 30;
        $widthx = 900;
        $heightx = 240;

        $pdfSigner->setAppearance(
          $x = 150,
          $y = 150,
          $width = 220,
          $height = 220,
          $page = $pagex,
          $spesimen = $png,
          $qr = ''
        );
      }
      // error_log(print_r($tte,true));
        $pdfSigner->setDirOutput('dokumen/regulasi/tmp');
        $pdfSigner->setAppearance(
            $x = $xx,
            $y = $yy,
            $width = $widthx,
            $height = $heightx,
            $page = $pagex,
            $spesimen = $tte,
            $qr = ''

        );
        
        $pdfSigner->setDocument($filepdf  );
        $hasil = $pdfSigner->sign(
            '1671065304650006',   //nik
            $_POST["pin"]    //passphrase
        );

    } 
}
$pdfSigner->setSuffixFileName($suffix);
$pdfSigner->moveFilesigned('dokumen/regulasi/tmp/'.$fileSurat,'dokumen/regulasi/signed/',$filename);
//END PDF BSRE

    if ($pin!=='') {
  
      // $query4 = mysqli_query($koneksi, "UPDATE surat SET fileSurat='$signedSurat.pdf',fileSurat_view='$signedSurat.pdf',status_respon='88',status_surat='finish' WHERE idSurat='$idSurat' AND tipe='REG'");
      // $updateresponse = mysqli_query($koneksi, "UPDATE response SET read_status='Y',read_dttm=NOW(), action='selesai' WHERE idResponse='$id'");
      // $insertfile = mysqli_query($koneksi,"INSERT INTO file (idSurat,file_path,file_nm,created_dttm) VALUES ('$idSurat','dokumen/regulasi/signed/','$signedSurat.pdf',NOW())");
      //NOTI WA
    $sys_apk=mysqli_query($koneksi, "SELECT nama,apikeys,website FROM sys_config");
    list($nmAplikasi,$apikeys,$site)=mysqli_fetch_array($sys_apk);
            
    $my_apikey = "$apikeys"; 
    $destination = "$telepon"; 
    $message = "_Assalamu’alaikum_
    Yth. Bpk/Ibu:
    *$nama* ($nmJabatan)

    Surat Anda telah _*disetujui dan ditandatangani*_ dengan keterangan sebagai berikut:

    Perihal: *$perihal*
    Klasifikasi Surat: *$kdKlas* | *$nmKlas*
    Jenis Surat: *$kdJenis* | *$nmJenis*
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

    // END NOTI WA
     // echo "<script>location.href='../../main.php?p=surat-regulasi-belum&approved=success'</script>";

    } else {
     // echo "<script>location.href='../../main.php?p=surat-regulasi-belum&approved=failed'</script>";
    }

  } else {
           echo "<script>location.href='../../main.php?p=surat-regulasi-belum&approved=failed2'</script>";
  }
 } else {
    echo "<script>location.href='../../main.php?p=surat-regulasi-belum&approved=failed3'</script>";
  }
}
?>