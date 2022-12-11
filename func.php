<?php
session_start();

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if(isset($_POST["encode_text"])){
    $target_path = create_new_uniq_dir("img/");

    $file_name = basename( $_FILES['fileToUpload']['name']);
    $target_path_file = $target_path.$file_name;
    $imageFileType_file = strtolower(pathinfo($target_path_file,PATHINFO_EXTENSION));

    if($imageFileType_file == "png" || $imageFileType_file == "jpg" || $imageFileType_file = "jpge"){
        move_uploaded_file($_FILES['fileToUpload']['tmp_name'], $target_path_file);
        encode_into_image($_POST["encode_text"], $target_path_file, $target_path, $_POST["format"]);
    }
    else{
        $_SESSION["error"] = "Nahraný soubor není obrázek!";
        header("Location:index.php");
    }

}

if(isset($_POST["decode"])){
    $target_path = create_new_uniq_dir("img/");

    $file_name = basename( $_FILES['defileToUpload']['name']);
    $target_path_file = $target_path.$file_name;
    $imageFileType_file = strtolower(pathinfo($target_path_file,PATHINFO_EXTENSION));

    if($imageFileType_file == "png" || $imageFileType_file == "jpg" || $imageFileType_file = "jpge"){
        move_uploaded_file($_FILES['defileToUpload']['tmp_name'], $target_path_file);
        decode_from_image($target_path_file);
    }
    else{
        $_SESSION["error"] = "Nahraný soubor není obrázek!";
        header("Location:index.php");
    }
}

function text_to_bin($text){
    // Encode the message into a binary string.
    $text = str_split($text);
    $binaryMessage = '';

    foreach ($text as $ch){
        $character = ord($ch);
        $binaryMessage .= str_pad(decbin($character), 8, '0', STR_PAD_LEFT);
    }

    // Inject the 'end of text' character into the string.
    $binaryMessage .= '01111110';

    return $binaryMessage;

}

function encode_into_image($message, $image_dic, $target_path, $format) {
    $binary_message = text_to_bin($message);

    $image_type_check = @exif_imagetype($image_dic);

    if ($image_type_check == IMAGETYPE_JPEG) {
        $image = imagecreatefromjpeg($image_dic);
    }
    else{
        $image = imagecreatefrompng($image_dic);
    }

    // získáme rozměry obrázku
    $width = imagesx($image);
    $height = imagesy($image);

    // projdeme všechny pixely obrázku
    for ($y = 0; $y < $height; $y++) {
        for ($x = 0; $x < $width; $x++) {

            // Konec pokud už není co ukládat
            if ($binary_message == null) {
                break 2;
            }

            // získáme barvy aktuálního pixelu
            $rgb = imagecolorat($image, $x, $y);
            $colors = imagecolorsforindex($image, $rgb);

            // B do bin
            $binaryBlue = str_pad(decbin($colors['blue']), 8, '0', STR_PAD_LEFT);

            // Náhrada posledního bitu
            $binaryBlue[strlen($binaryBlue) - 1] = substr($binary_message, 0, 1);
            $binary_message = substr($binary_message, 1);

            // Zpět do desitkvé
            $nb = bindec($binaryBlue);

            // Uložení bitu
            $newColor = imagecolorallocatealpha($image, $colors['red'], $colors['green'], $nb, $colors['alpha']);
            imagesetpixel($image, $x, $y, $newColor);
        }
    }

    $newImage = $target_path."done.".$format;
    imagepng($image, $newImage, 9);
    imagedestroy($image);

    header("Location:done.php?img=".$newImage);
}


function decode_from_image($image_dic) {

    $image_type_check = @exif_imagetype($image_dic);

    if ($image_type_check == IMAGETYPE_PNG) {
        $image = imagecreatefrompng($image_dic);
    }
    else{
        $image = imagecreatefrombmp($image_dic);
    }

    $width = imagesx($image);
    $height = imagesy($image);

    $bit = "";
    $final = [];

    for ($y = 0; $y < $height; $y++) {
        for ($x = 0; $x < $width; $x++) {
            if (strlen($bit) == 8) {
                if ($bit == '01111110') {
                    break 2;
                }
                else{
                    array_push($final, $bit);
                    $bit = "";
                }
            }

            // Extract the colour.
            $rgb = imagecolorat($image, $x, $y);
            $colors = imagecolorsforindex($image, $rgb);

            $blue = $colors['blue'];
            $binaryBlue = decbin($blue);

            $bit .= $binaryBlue[strlen($binaryBlue) - 1];
        }
    }

    $done = "";
    foreach ($final as $f){
        $done .= chr(bindec($f));
    }
    echo $done;

    header("Location:decode.php?done=".$done);
}

function create_new_uniq_dir($path){
    $uniq = uniqid(rand(), true)."/";
    $target_path = $path.$uniq;

    mkdir($target_path, 0700);
    return $target_path;
}
