<?php
// og.php — 动态生成分享图
require __DIR__ . '/lib/db_connect.php';

$scope = $_GET['scope'] ?? 'test';

$title    = 'DoFun心理实验空间';
$subtitle = '小小的题目，关于你的大方向。';

if ($scope === 'test') {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($id > 0) {
        $stmt = $pdo->prepare("SELECT * FROM tests WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        if ($test = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $title    = mb_substr($test['title'], 0, 18);
            $subtitle = mb_substr($test['description'] ?? '关于你的性格的小实验。', 0, 30);
        }
    }
} elseif ($scope === 'result') {
    $testId = isset($_GET['test_id']) ? (int)$_GET['test_id'] : 0;
    $code   = $_GET['code'] ?? '';
    if ($testId > 0 && $code !== '') {
        $stmt = $pdo->prepare("SELECT * FROM tests WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $testId]);
        $test = $stmt->fetch(PDO::FETCH_ASSOC);

        $stmt2 = $pdo->prepare("SELECT * FROM results WHERE test_id = :tid AND code = :code LIMIT 1");
        $stmt2->execute([':tid' => $testId, ':code' => $code]);
        $result = $stmt2->fetch(PDO::FETCH_ASSOC);

        if ($test && $result) {
            $title    = mb_substr($result['title'], 0, 18);
            $subtitle = mb_substr($test['title'], 0, 26);
        }
    }
}

$width  = 1200;
$height = 630;
$image  = imagecreatetruecolor($width, $height);

$bgStart = [15, 23, 42];
$bgEnd   = [30, 64, 175];

for ($y = 0; $y < $height; $y++) {
    $ratio = $y / $height;
    $r = (int)($bgStart[0] * (1 - $ratio) + $bgEnd[0] * $ratio);
    $g = (int)($bgStart[1] * (1 - $ratio) + $bgEnd[1] * $ratio);
    $b = (int)($bgStart[2] * (1 - $ratio) + $bgEnd[2] * $ratio);
    $lineColor = imagecolorallocate($image, $r, $g, $b);
    imageline($image, 0, $y, $width, $y, $lineColor);
}

$dotColors = [
    imagecolorallocatealpha($image, 129, 140, 248, 90),
    imagecolorallocatealpha($image, 236, 72, 153, 90),
];

for ($i = 0; $i < 12; $i++) {
    $c = $dotColors[$i % 2];
    $x = rand(50, $width - 50);
    $y = rand(50, $height - 50);
    $r = rand(40, 140);
    imagefilledellipse($image, $x, $y, $r, $r, $c);
}

$cardColor = imagecolorallocatealpha($image, 15, 23, 42, 70);
imagefilledroundrect($image, 80, 90, $width - 80, $height - 90, 30, $cardColor);

$borderColor = imagecolorallocatealpha($image, 148, 163, 184, 80);
imagerectangle($image, 80, 90, $width - 80, $height - 90, $borderColor);

$fontRegular = __DIR__ . '/fonts/SourceHanSansCN-Regular.otf';
$fontBold    = __DIR__ . '/fonts/SourceHanSansCN-Bold.otf';
if (!file_exists($fontBold)) {
    $fontBold = $fontRegular;
}
if (!file_exists($fontRegular)) {
    $fontRegular = __DIR__ . '/fonts/SourceHanSansCN-Regular.ttf';
}

$white  = imagecolorallocate($image, 249, 250, 251);
$muted  = imagecolorallocate($image, 148, 163, 184);
$accent = imagecolorallocate($image, 196, 181, 253);

$logoDot = imagecolorallocate($image, 129, 140, 248);
imagefilledellipse($image, 120, 140, 16, 16, $logoDot);

imagettftext($image, 22, 0, 150, 148, $white, $fontBold, 'DoFun心理实验空间');
imagettftext($image, 16, 0, 150, 180, $muted, $fontRegular, 'Small psychological experiments about you.');

$titleLines = df_wrap_text($title, $fontBold, 42, 900);
$textY = 260;
foreach ($titleLines as $line) {
    imagettftext($image, 42, 0, 150, $textY, $white, $fontBold, $line);
    $textY += 56;
}

imagettftext($image, 20, 0, 150, $textY + 20, $accent, $fontRegular, $subtitle);

imagettftext($image, 16, 0, 150, $height - 120, $muted, $fontRegular, 'Scan / click to start your tiny experiment.');
imagettftext($image, 14, 0, 150, $height - 90, $muted, $fontRegular, 'fun.dofun.fun');

header('Content-Type: image/png');
imagepng($image);
imagedestroy($image);

function imagefilledroundrect($im, $x1, $y1, $x2, $y2, $radius, $col): void
{
    imagefilledrectangle($im, $x1 + $radius, $y1, $x2 - $radius, $y2, $col);
    imagefilledrectangle($im, $x1, $y1 + $radius, $x2, $y2 - $radius, $col);
    imagefilledellipse($im, $x1 + $radius, $y1 + $radius, $radius * 2, $radius * 2, $col);
    imagefilledellipse($im, $x2 - $radius, $y1 + $radius, $radius * 2, $radius * 2, $col);
    imagefilledellipse($im, $x1 + $radius, $y2 - $radius, $radius * 2, $radius * 2, $col);
    imagefilledellipse($im, $x2 - $radius, $y2 - $radius, $radius * 2, $radius * 2, $col);
}

function df_wrap_text($text, $font, $size, $maxWidth): array
{
    $lines = [];
    $current = '';
    $len = mb_strlen($text);
    for ($i = 0; $i < $len; $i++) {
        $char = mb_substr($text, $i, 1);
        $tmp = $current . $char;
        $box = imagettfbbox($size, 0, $font, $tmp);
        $w = $box[2] - $box[0];
        if ($w > $maxWidth && $current !== '') {
            $lines[] = $current;
            $current = $char;
        } else {
            $current = $tmp;
        }
    }
    if ($current !== '') {
        $lines[] = $current;
    }
    return $lines;
}
