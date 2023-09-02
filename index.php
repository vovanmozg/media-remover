<?php

define(WIN_DIR, "F:\\media");
define(LINUX_DIR, "/mnt/media");

ini_set('memory_limit', '256M');

function transformPathToURL($path) {
    $baseDir = WIN_DIR;
    return str_replace('\\', '/', str_replace($baseDir, 'http://localhost:8000/?image=', $path));
}

function handleFileDeletion($path) {
    return;
    if (file_exists($path)) {
        unlink($path);
        return "<script>alert('Deleted: " . $path . "');</script>";
    }
    return "<script>alert('Error deleting: " . $path . "');</script>";
}

function handleImageDisplay($path) {
    $fullPath = LINUX_DIR . "/" . $path;
    $cachePath = './cache/' . md5($path) . '.jpg';

    if (file_exists($cachePath)) {
        header("Content-type: image/jpeg");
        readfile($cachePath);
        return;
    }

    if (file_exists($fullPath)) {
        $image = imagecreatefromjpeg($fullPath);
        $width = imagesx($image);
        $height = imagesy($image);

        $new_width = 200;
        $new_height = floor($height * ($new_width / $width));

        $tmp_img = imagecreatetruecolor($new_width, $new_height);
        imagecopyresampled($tmp_img, $image, 0, 0, 0, 0, $new_width, $new_height, $width, $height);

        // Save to cache
        imagejpeg($tmp_img, $cachePath);

        // Send to browser
        header("Content-type: image/jpeg");
        imagejpeg($tmp_img);
        imagedestroy($image);
        imagedestroy($tmp_img);
    } else {
        header("HTTP/1.0 404 Not Found");
        echo "Image not found";
    }
}

$data = json_decode(file_get_contents("./actions.json"), true);
$max_entries = min(count($data['inside_new_full_dups']), 20);

if (isset($_GET['delete'])) {
    echo handleFileDeletion($_GET['delete']);
} elseif (isset($_GET['image'])) {
    handleImageDisplay($_GET['image']);
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Duplicate Remover</title>
    <style>
        div {
            margin-bottom: 20px;
        }
    </style>
</head>
<body>

<div id="duplicatesContainer">
    <?php for ($i = 0; $i < $max_entries; $i++):
      $item = $data["inside_new_full_dups"][$i];
      $original = $item["original"];
      $dup = array_merge($item["from"], $item["dup"]);

      $originalPath = $original["real_path"];
      $dupPath = $dup["real_path"];

      $originalSize = filesize($originalPath);
      $dupSize = filesize($dupPath);

      $displayOriginalPath = str_replace("\\", "<br />", $originalPath);
      $displayDupPath = str_replace("\\", "<br />", $dupPath);

      $originalImgHeight = 200 * $original["height"] / $original["width"];
      if (!$originalImgHeight) {
        $originalImgHeight = 100;
      }
      $dupImgHeight = 200 * $dup["height"] / $dup["width"];
      if (!($dupImgHeight > 0)) {
        $dupImgHeight = $originalImgHeight;
      }

      $originalImageDetails = getimagesize($originalPath);
      $dupImageDetails = getimagesize($dupPath);
    ?>
        <div style="clear:both;">
          <div style="float: left; width: 20px; height: 100px; background-color: red">1</div>
          <div style="float: left; width: 300px;">
            <img src="<?= transformPathToURL($original["real_path"]) ?>" border="1" style="width: 200px; height: <?= $originalImgHeight ?>px;">
            <p>
              <?= $original["width"] ?>x<?= $original["height"] ?><br />
              <?= $original["size"] ?><br />
              <?= $displayOriginalPath ?>
            </p>
          </div>
          <div style="float: left; width: 300px;">
            <img src="<?= transformPathToURL($item["dup"]["real_path"]) ?>" border="1" style="width: 200px; height: <?= $dupImgHeight ?>px;">
            <p>
              <?= $dup["width"] ?>x<?= $dup["height"] ?><br />
              <?= $dup["size"] ?><br />
              <?= $displayDupPath ?>
            </p>

          </div>
          <a href="?delete=<?= urlencode($item["dup"]["real_path"]) ?>">Delete Duplicate</a>
        </div>
    <?php endfor; ?>
</div>

</body>
</html>
