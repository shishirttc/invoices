<?php
$ftp_server = "ftpupload.net";
$ftp_user = "ezyro_41758256";
$ftp_pass = "7726e631f";

$conn_id = ftp_connect($ftp_server) or die("Could not connect to $ftp_server");
$login_result = ftp_login($conn_id, $ftp_user, $ftp_pass);

if (!$login_result) {
    die("FTP login failed!");
}

ftp_pasv($conn_id, true);

// Function to upload directory
function upload_directory($conn_id, $local_dir, $remote_dir) {
    @ftp_mkdir($conn_id, $remote_dir);
    $files = scandir($local_dir);
    foreach ($files as $file) {
        if ($file == '.' || $file == '..' || $file == '.git' || $file == 'ftp_upload.php') continue;
        
        $local_path = $local_dir . '/' . $file;
        $remote_path = $remote_dir . '/' . $file;
        
        if (is_dir($local_path)) {
            upload_directory($conn_id, $local_path, $remote_path);
        } else {
            if (ftp_put($conn_id, $remote_path, $local_path, FTP_BINARY)) {
                echo "Successfully uploaded $local_path to $remote_path\n";
            } else {
                echo "There was a problem while uploading $local_path\n";
            }
        }
    }
}

// Start uploading from htdocs or the root folder
// Note: On ezyro/infinityfree, the web folder is usually 'htdocs'
$remote_base = "htdocs"; 
upload_directory($conn_id, ".", $remote_base);

ftp_close($conn_id);
echo "Upload completed!";
?>