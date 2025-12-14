<?php
// src/train.php
require __DIR__ . '/../vendor/autoload.php';

use Phpml\Classification\DecisionTree;
use Phpml\ModelManager;

// Paths
$csvFile = __DIR__ . '/dataset.csv';
$modelsDir = __DIR__ . '/../models';
$jacketModelFile = $modelsDir . '/jacket.model';
$umbrellaModelFile = $modelsDir . '/umbrella.model';

// Read CSV
if (!file_exists($csvFile)) {
    echo "Dataset not found at $csvFile\n";
    exit(1);
}

$samples = [];
$jacketLabels = [];
$umbrellaLabels = [];

if (($handle = fopen($csvFile, 'r')) !== false) {
    $header = fgetcsv($handle);
    while (($row = fgetcsv($handle)) !== false) {
        // expect: temp,humidity,wind,precip,condition,jacket,umbrella
        // defensive: skip malformed rows
        if (count($row) < 7) continue;
        $temp = (float) $row[0];
        $humidity = (float) $row[1];
        $wind = (float) $row[2];
        $precip = (float) $row[3];
        $condition = (int) $row[4];
        $jacket = strtolower(trim($row[5])) === 'yes' ? 'yes' : 'no';
        $umbrella = strtolower(trim($row[6])) === 'yes' ? 'yes' : 'no';

        $samples[] = [$temp, $humidity, $wind, $precip, $condition];
        $jacketLabels[] = $jacket;
        $umbrellaLabels[] = $umbrella;
    }
    fclose($handle);
}

if (empty($samples)) {
    echo "No training samples loaded.\n";
    exit(1);
}

// Train classifiers
echo "Training models with " . count($samples) . " samples...\n";
$jacketClassifier = new DecisionTree();
$jacketClassifier->train($samples, $jacketLabels);

$umbrellaClassifier = new DecisionTree();
$umbrellaClassifier->train($samples, $umbrellaLabels);

// Save models
if (!is_dir($modelsDir)) {
    mkdir($modelsDir, 0755, true);
}

$modelManager = new ModelManager();
$modelManager->saveToFile($jacketClassifier, $jacketModelFile);
$modelManager->saveToFile($umbrellaClassifier, $umbrellaModelFile);

echo "Training complete. Models saved to:\n - $jacketModelFile\n - $umbrellaModelFile\n";
