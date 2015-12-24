<?php

use module\UploadFile;

$max = 100 * 1024;
$result = [];

if ( isset( $_POST[ 'upload' ] ) )
{
  require_once 'src/module/UploadFile.php';

  $destination = __DIR__ . '/uploaded/';

  try
  {
    $upload = new UploadFile( $destination );
    $upload->setMaxSize( $max );
    $upload->allowAllTypes();
    $upload->upload();

    $result = $upload->getMessages();
  }
  catch ( Exception $e )
  {
    $result[] = $e->getMessage();
  }
}
?>

<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>File Uploads</title>
  <link href="styles/form.css" rel="stylesheet" type="text/css">
</head>
<body>
<h1>Uploading Files</h1>

<? if ( $result ) : ?>
  <ul class="result">
    <? foreach( $result as $message ) : ?>
      <li><?= $message ?></li>
    <? endforeach ?>
  </ul>
<? endif ?>

<form action="<?= $_SERVER[ 'PHP_SELF' ] ?>" method="post" enctype="multipart/form-data">
  <p>
    <input type="hidden" name="MAX_FILE_SIZE" value="<?= $max ?>"> <label for="filename">Select File:</label>
    <input type="file" name="filename[]" id="filename" multiple>
  </p>

  <p>
    <input type="submit" name="upload" value="Upload File">
  </p>
</form>

</body>
</html>