<?php
define('WIN_DIR', "F:\\media");
define('LINUX_DIR', "/mnt/media");
define('PAGE_SIZE', 5);
define('DEFAULT_THUMB_WIDTH', 200);
define('SMALLER_THUMB_RATIO', 0.8);

ini_set('memory_limit', '256M');

function transformPathToURL($path) {
    return str_replace('\\', '/', str_replace(WIN_DIR, 'http://localhost:8000/?image=', $path));
}

function handleFileDeletion($path, $type, $destination = 'removed') {
    if ($destination == 'removed') {
      $destinationDir = '/mnt/media/removed';
    } elseif ($destination == 'archive') {
      $destinationDir = '/mnt/media/archive';
    } else {
      return "Invalid destination: " . $destination . "<br>";
    }


    // Получаем путь, который нужно сохранить
    $relativePath = str_replace(LINUX_DIR, '', $path);
    $destinationPath = $destinationDir . $relativePath;

    // Проверяем, существует ли каталог назначения, и создаем его, если он отсутствует
    $destinationFolder = dirname($destinationPath);
    if (!is_dir($destinationFolder)) {
        mkdir($destinationFolder, 0755, true); // true для рекурсивного создания каталогов
    }

    if (!file_exists($path)) {
      return "File does not exist: " . $path . ", destination:" . $destinationPath . "<br>";
    }

    if (rename($path, $destinationPath)) {
        return "Moved $type: " . $path . " to " . $destinationPath . "<br>";
    } else {
        return "Error moving $type: " . $path . " to " . $destinationPath . "<br>";
    }
}

function handleImageDisplay($path) {
    $fullPath = LINUX_DIR . "/" . $path;
    $cachePath = './cache/' . md5($path) . '.jpg';

    if (file_exists($cachePath) && file_exists($fullPath)) {
      header("Content-type: image/jpeg");
        readfile($cachePath);
        return;
    }

    if (file_exists($fullPath)) {
        $image = imagecreatefromjpeg($fullPath);
        $width = imagesx($image);
        $height = imagesy($image);

        $new_width = DEFAULT_THUMB_WIDTH;
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

function findItemByTo($data, $to) {
    foreach ($data as $key => $value) {
        foreach ($value as $item) {
            if ($item['to'] == $to) {
                return $item;
            }
        }
    }
    return null;
}

if (isset($_GET['image'])) {
    handleImageDisplay($_GET['image']);
    exit;
}

$data = json_decode(file_get_contents("./actions.json"), true);
$results = [];
// Check if imageAction is set in POST data
if (isset($_POST['imageAction']) && is_array($_POST['imageAction'])) {
  $processingActions = $_POST['imageAction'];

  // Iterate through each image and its action
  foreach ($processingActions as $to => $action) {
    $item = findItemByTo($data, $to);

    switch ($action) {
      case 'remove-original':
        $linuxPath = str_replace('\\', '/', str_replace(WIN_DIR, LINUX_DIR, $item['original']['real_path']));
        $results[] = handleFileDeletion($linuxPath, 'original');
        break;
      case 'remove-dup':
        $linuxPath = str_replace('\\', '/', str_replace(WIN_DIR, LINUX_DIR, $item['dup']['real_path']));
        $results[] = handleFileDeletion($linuxPath, 'dup');
//                  echo $result . "<br>";
        break;
      case 'remove-both':
        $linuxPath = str_replace('\\', '/', str_replace(WIN_DIR, LINUX_DIR, $item['original']['real_path']));
        $results[] = handleFileDeletion($linuxPath, 'original');
        $linuxPath = str_replace('\\', '/', str_replace(WIN_DIR, LINUX_DIR, $item['dup']['real_path']));
        $results[] = handleFileDeletion($linuxPath, 'dup');
        break;
      case 'archive-both':
        $linuxPath = str_replace('\\', '/', str_replace(WIN_DIR, LINUX_DIR, $item['original']['real_path']));
        $results[] = handleFileDeletion($linuxPath, 'original', 'archive');
        $linuxPath = str_replace('\\', '/', str_replace(WIN_DIR, LINUX_DIR, $item['dup']['real_path']));
        $results[] = handleFileDeletion($linuxPath, 'dup', 'archive');
        break;
      default:
        echo "No action specified for " . $linuxPath . "<br>";
        break;
    }
  }
}

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

$max_entries = min(count($data[$selected_area]), PAGE_SIZE);


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
        #duplicatesContainer .infoContainer {
            float: left;
            width: 300px;
        }
        #duplicatesContainer img {
            border: 1px solid #555;
            border-bottom-width: 5px;
            width: <?= DEFAULT_THUMB_WIDTH ?>px;
            float: left;
        }
        #duplicatesContainer img.smaller {
            width: <?= DEFAULT_THUMB_WIDTH * SMALLER_THUMB_RATIO ?>px;
        }
        #duplicatesContainer .remove-dup .dup img,
        #duplicatesContainer .remove-original .original img,
        #duplicatesContainer .remove-both .original img,
        #duplicatesContainer .remove-both .dup img {
            border-bottom: 5px solid #f00;
        }
        #duplicatesContainer .archive-both img {
            border-bottom: 5px solid yellow;
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

        div.marker {
          border: 1px solid #000;
          width: 20px;
          float: left;
        }
    </style>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            let items = document.querySelectorAll('.item');
            let currentIndex = 0;

            let actions = ['remove-original', 'remove-dup', 'remove-both', 'archive-both'];
            //let currentAction = 1;

            // Update the hidden input value when action changes
//             function updateActionInput() {
//                 items[currentIndex].querySelector('input[type="hidden"]').value = actions[currentAction];
//             }

            function selectCurrentItem() {
                items.forEach((item, index) => {
                    if (index === currentIndex) {
                        item.classList.add('current');
                        item.scrollIntoView({ behavior: 'instant', block: 'center' });
                    } else {
                        item.classList.remove('current');
                    }
                });
            }

            function selectAction(direction) {
                items.forEach((item, index) => {
                    if (index === currentIndex) {
                        let currentAction = actions.indexOf(item.querySelector('input[type="hidden"]').value);

                        if (direction === 1 && currentAction < actions.length - 1) {
                            currentAction++;
                        } else if (direction === -1 && currentAction > 0) {
                            currentAction--;
                        } else {
                            return;
                        }

                        item.querySelector('input[type="hidden"]').value = actions[currentAction];
                        item.querySelector('.action').innerHTML = actions[currentAction];
                        item.classList.remove(...actions);
                        item.classList.add(actions[currentAction]);
                    }
                });
            }

            document.addEventListener('keydown', function(e) {
                if (e.ctrlKey) {
                    switch (e.keyCode) {
                        case 38: // up arrow
                            if (currentIndex > 0) currentIndex--;
                            selectCurrentItem();
                            e.preventDefault();
                            return;
                        case 40: // down arrow
                            if (currentIndex < items.length - 1) currentIndex++;
                            selectCurrentItem();
                            e.preventDefault();
                            return;
                        case 37: // left arrow
                            //if (currentAction > 0) currentAction--;
                            selectAction(-1);
                            e.preventDefault();
                            break;
                        case 39: // right arrow
                            //if (currentAction < actions.length - 1) currentAction++;
                            selectAction(1);
                            e.preventDefault();
                            break;
                    }
                };
            });
            // processing mouse clicks
            items.forEach((item, index) => {
                item.addEventListener('click', function(e) {
                    currentIndex = index;
                    selectCurrentItem();
                });
            });
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

<form method="post" action="" id="imageActionsForm">
  <div id="duplicatesContainer">
    <?php

    $allIterator = 0;
    $displayIterator = 0;
    while (true) {
//       echo "alliterator: $allIterator, datalen: " . count($data[$selected_area]) . "<br>";
//       echo "displayIterator: $displayIterator, max_entries: $max_entries<br>";
      if ($allIterator >= count($data[$selected_area])) {

        break;
      }

      if ($displayIterator >= $max_entries) {

        break;
      }

      $i = $allIterator;

      $item = $data[$selected_area][$i];
      $o = $item["original"];
      $d = array_merge($item["from"], $item["dup"]);

      $originalPath = $o["real_path"];
      $dupPath = $d["real_path"];

      $linuxOriginalPath = str_replace('\\', '/', str_replace(WIN_DIR, LINUX_DIR, $originalPath));
//       $cacheOriginalPath = './cache/' . md5($linuxOriginalPath) . '.jpg';
      $linuxDupPath = str_replace('\\', '/', str_replace(WIN_DIR, LINUX_DIR, $dupPath));
//       $cacheDupPath = './cache/' . md5($linuxDupPath) . '.jpg';

      $oExists = file_exists($linuxOriginalPath);
      $dExists = file_exists($linuxDupPath);

      if (!$oExists || !$dExists) {
          $allIterator++;
          continue;
      }
      $linuxDupPath = str_replace('\\', '/', str_replace(WIN_DIR, LINUX_DIR, $dupPath));


      $originalSize = filesize($linuxOriginalPath);
      $dupSize = filesize($linuxDupPath);

      $displayOriginalPath = str_replace("/", "<br />", $o['full_path']);
      $displayDupPath = str_replace("/", "<br />", $d['full_path']);

      $originalImgHeight = DEFAULT_THUMB_WIDTH * $o["height"] / $o["width"];
      if (!$originalImgHeight) {
        $originalImgHeight = DEFAULT_THUMB_WIDTH / 2;
      }
      $dupImgHeight = DEFAULT_THUMB_WIDTH * $d["height"] / $d["width"];
      if (!($dupImgHeight > 0)) {
        $dupImgHeight = $originalImgHeight;
      }

      if ($o["width"] > $d["width"]) {
        $imgDupClass = "smaller";
        $imgOriginalClass = "";
        $dupImgHeight *= SMALLER_THUMB_RATIO;
      } elseif ($o["width"] < $d["width"]) {
        $imgOriginalClass = "smaller";
        $imgDupClass = "";
        $originalImgHeight *= SMALLER_THUMB_RATIO;
      } else {
        $imgOriginalClass = "";
        $imgDupClass = "";
      }

      $originalImageDetails = getimagesize($linuxOriginalPath);
      $dupImageDetails = getimagesize($linuxDupPath);

      $originalImgUrl = transformPathToURL($o["real_path"]);
      $dupImgUrl = transformPathToURL($d["real_path"]);

      $originalPath = $o["real_path"];
      $fullPathKey = urlencode($originalPath); // URL-encode to ensure valid HTML

      $originalHeavierSameResolution = $o["width"] == $d["width"] && $o["height"] == $d["height"] && $o["size"] > $d["size"];
      $dupHeavierSameResolution = $o["width"] == $d["width"] && $o["height"] == $d["height"] && $d["size"] > $o["size"];


      $displayIterator++;
      $allIterator++;
    ?>
      <div class="item remove-dup">
        <div class="infoContainer original">
          <div class="imageContainer">
            <img src="<?= $originalImgUrl ?>" class="<?= $imgOriginalClass ?>" style="height: <?= $originalImgHeight ?>px;">
            <div class="marker marker-original"><?= $originalHeavierSameResolution ? "⚓" : "" ?></div>
            <div class="clear"></div>
          </div>
          <p>
            <?= $o["phash"] != $d["phash"] ? $o["phash"] : '' ?><br />
            <?= $o["width"] ?>x<?= $o["height"] ?><br />
            <?= $o["size"] ?><br />
            <?= $displayOriginalPath ?>
          </p>
        </div>
        <div class="infoContainer dup">
          <div class="imageContainer">
            <div class="marker marker-dup"><?= $dupHeavierSameResolution ? "⚓" : "" ?></div>
            <img src="<?= $dupImgUrl ?>" class="<?= $imgDupClass ?>" style="height: <?= $dupImgHeight ?>px;">
            <div class="clear"></div>
          </div>
          <p>
            <?= $o["phash"] != $d["phash"] ? $d["phash"] : '' ?><br />
            <?= $d["width"] ?>x<?= $d["height"] ?><br />
            <?= $d["size"] ?><br />
            <?= $displayDupPath ?>
          </p>
        </div>
        <div class="action">remove-dup</div>
        <div class="clear"></div>
        <!-- Hidden input to store action for this image -->
        <input type="hidden" name="imageAction[<?= $item['to'] ?>]" value="remove-dup">
      </div>
    <?php } ?>
    <!-- Submit button -->
    <input type="submit" value="Submit Actions">
  </div>
</form>

<div id="results">
  <?php foreach ($results as $result): ?>
    <?= $result ?>
  <?php endforeach; ?>
</div>
</body>
</html>

