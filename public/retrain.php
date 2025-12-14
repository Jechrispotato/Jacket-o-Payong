<?php
// public/retrain.php
require __DIR__ . '/../vendor/autoload.php';

use Phpml\Classification\DecisionTree;
use Phpml\ModelManager;

$modelsDir = __DIR__ . '/../models';
$jacketModelFile = $modelsDir . '/jacket.model';
$umbrellaModelFile = $modelsDir . '/umbrella.model';
$messages = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_FILES['csv']) || $_FILES['csv']['error'] !== UPLOAD_ERR_OK) {
        $messages[] = "Upload failed. Please choose a CSV file.";
    } else {
        $tmp = $_FILES['csv']['tmp_name'];
        // move uploaded to src/dataset.csv for consistent training
        if (!move_uploaded_file($tmp, __DIR__ . '/../src/dataset.csv')) {
            $messages[] = "Failed to save uploaded file.";
        } else {
            // run training logic (same as src/train.php)
            $csvFile = __DIR__ . '/../src/dataset.csv';
            $samples = [];
            $jacketLabels = [];
            $umbrellaLabels = [];
            if (($handle = fopen($csvFile, 'r')) !== false) {
                $header = fgetcsv($handle);
                while (($row = fgetcsv($handle)) !== false) {
                    if (count($row) < 7) continue;
                    $samples[] = [(float)$row[0], (float)$row[1], (float)$row[2], (float)$row[3], (int)$row[4]];
                    $jacketLabels[] = strtolower(trim($row[5])) === 'yes' ? 'yes' : 'no';
                    $umbrellaLabels[] = strtolower(trim($row[6])) === 'yes' ? 'yes' : 'no';
                }
                fclose($handle);
            }

            if (count($samples) === 0) {
                $messages[] = "No valid rows in CSV.";
            } else {
                if (!is_dir($modelsDir)) mkdir($modelsDir, 0755, true);
                $jClassifier = new DecisionTree();
                $jClassifier->train($samples, $jacketLabels);
                $uClassifier = new DecisionTree();
                $uClassifier->train($samples, $umbrellaLabels);

                $m = new ModelManager();
                $m->saveToFile($jClassifier, $jacketModelFile);
                $m->saveToFile($uClassifier, $umbrellaModelFile);

                $messages[] = "Retraining complete. Models saved.";
            }
        }
    }
}
?>
<!doctype html>
<html>
<head><meta charset="utf-8"><title>Retrain Models</title></head>
<body>
  <h1>Retrain models (admin)</h1>
  <?php foreach ($messages as $msg) echo "<p>" . htmlspecialchars($msg) . "</p>"; ?>
  <form method="post" enctype="multipart/form-data">
    <label>Upload CSV (format: temp,humidity,wind,precip,condition,jacket,umbrella)
      <input type="file" name="csv" accept=".csv" required>
    </label>
    <button type="submit">Upload & Retrain</button>
  </form>
  <p><a href="index.php">Back to front page</a></p>
</body>
</html>
