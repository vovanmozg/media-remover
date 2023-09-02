<?php
define('WIN_DIR', "F:\\media");
define('LINUX_DIR', "/mnt/media");

ini_set('memory_limit', '256M');

function transformPathToURL($path) {
    return str_replace('\\', '/', str_replace(WIN_DIR, 'http://localhost:8000/?image=', $path));
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


if (isset($_GET['delete'])) {
    echo handleFileDeletion($_GET['delete']);
    exit;
} elseif (isset($_GET['image'])) {
    handleImageDisplay($_GET['image']);
    exit;
}

$data = json_decode(file_get_contents("./actions.json"), true);

// Check for the selected area
$selected_area = 'inside_new_full_dups'; // Default
if (isset($_COOKIE['selected_area']) && array_key_exists($_COOKIE['selected_area'], $data)) {
    $selected_area = $_COOKIE['selected_area'];
}

// If a new area is selected
if (isset($_POST['data_area']) && array_key_exists($_POST['data_area'], $data)) {
    $selected_area = $_POST['data_area'];
    setcookie('selected_area', $selected_area, time() + (86400 * 30), "/"); // 86400 = 1 day
}

$max_entries = min(count($data[$selected_area]), 20);


?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Duplicate Remover</title>
    <style>
        div.item {
            margin-bottom: 20px;
            border: 2px solid #eee;
            padding: 10px;
        }
        .clear {
          clear: both;
        }
        #duplicatesContainer .imageContainer {
            float: left;
            width: 300px;
        }
        #duplicatesContainer img {
            border: 1px solid #555;
            border-bottom-width: 5px;
            width: 200px;
        }
        #duplicatesContainer .remove-dup .dup img,
        #duplicatesContainer .remove-original .original img {
            border-bottom: 5px solid #f00;
        }
        #duplicatesContainer .remove-dup .original img,
                #duplicatesContainer .remove-original .dup img {
                    border-bottom: 5px solid transparent;
                }

        div.current {
            border-color: #eeffee;
            background-color: #eeffee;
        }
        div.remove-dup {

        }
        div.remove-original {

        }
    </style>
    <script>
            document.addEventListener("DOMContentLoaded", function() {
                let items = document.querySelectorAll('.item');
                let currentIndex = 0;
                let actionClasses = ['remove-original', 'remove-dup'];
                let actions = ['Delete Original', 'Delete Duplicate'];
                let currentAction = 1;

                function updateCurrentItem() {
                    items.forEach((item, index) => {
                        if (index === currentIndex) {
                            item.classList.add('current');
                            item.classList.remove(...actionClasses);
                            item.classList.add(actionClasses[currentAction]);
                            item.querySelector('.action').textContent = actions[currentAction];
                        } else {
                            item.classList.remove('current');
                        }
                    });
                }

                document.addEventListener('keydown', function(e) {
                    if (e.ctrlKey) {
                        switch (e.keyCode) {
                            case 38: // up arrow
                                if (currentIndex > 0) currentIndex--;
                                updateCurrentItem();
                                e.preventDefault();
                                break;
                            case 40: // down arrow
                                if (currentIndex < items.length - 1) currentIndex++;
                                updateCurrentItem();
                                e.preventDefault();
                                break;
                            case 37: // left arrow
                                if (currentAction > 0) currentAction--;
                                updateCurrentItem();
                                e.preventDefault();
                                break;
                            case 39: // right arrow
                                if (currentAction < actionClasses.length - 1) currentAction++;
                                updateCurrentItem();
                                e.preventDefault();
                                break;
                        }
                    }
                });

                updateCurrentItem();
            });
        </script>
</head>
<body>
<form method="post" action="">
    <select name="data_area" onchange="this.form.submit();">
        <?php foreach ($data as $key => $value): ?>
            <option value="<?= $key ?>" <?= $key == $selected_area ? 'selected' : '' ?>><?= $key ?></option>
        <?php endforeach; ?>
    </select>
</form>

<div id="duplicatesContainer">
    <?php for ($i = 0; $i < $max_entries; $i++):
      $item = $data[$selected_area][$i];
      $o = $item["original"];
      $d = array_merge($item["from"], $item["dup"]);

      $originalPath = $o["real_path"];
      $dupPath = $d["real_path"];

      $originalSize = filesize($originalPath);
      $dupSize = filesize($dupPath);

      $displayOriginalPath = str_replace("/", "<br />", $o['full_path']);
      $displayDupPath = str_replace("/", "<br />", $d['full_path']);

      $originalImgHeight = 200 * $o["height"] / $o["width"];
      if (!$originalImgHeight) {
        $originalImgHeight = 100;
      }
      $dupImgHeight = 200 * $d["height"] / $d["width"];
      if (!($dupImgHeight > 0)) {
        $dupImgHeight = $originalImgHeight;
      }

      $originalImageDetails = getimagesize($originalPath);
      $dupImageDetails = getimagesize($dupPath);

      $originalImgUrl = transformPathToURL($o["real_path"]);
      $dupImgUrl = transformPathToURL($d["real_path"]);
    ?>
        <div class="item remove-dup">
          <!-- div class="marker"></div -->
          <div class="imageContainer original">
            <img src="<?= $originalImgUrl ?>" style="height: <?= $originalImgHeight ?>px;">
            <p>
              <?= $o["width"] ?>x<?= $o["height"] ?><br />
              <?= $o["size"] ?><br />
              <?= $displayOriginalPath ?>
            </p>
          </div>
          <div class="imageContainer dup">
            <img src="<?= $dupImgUrl ?>" style="height: <?= $dupImgHeight ?>px;">
            <p>
              <?= $d["width"] ?>x<?= $d["height"] ?><br />
              <?= $d["size"] ?><br />
              <?= $displayDupPath ?>
            </p>
          </div>
          <div class="action">Delete Duplicate</div>
          <div class="clear"></div>
        </div>
    <?php endfor; ?>
</div>
</body>
</html>

