<?php

require __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/documents.php';
requireLogin();

$vendorAutoload = __DIR__ . '/vendor/autoload.php';

if (!is_file($vendorAutoload)) {
    http_response_code(500);
    exit('PhpSpreadsheet не установлен (нет vendor/autoload.php).');
}

require_once $vendorAutoload;

$db = getDb();

$employees = $db->query('SELECT id, full_name, citizenship, department FROM employees ORDER BY full_name')->fetchAll();

$docCounts = [];
$nearestExpireByEmployee = [];

foreach ($db->query('SELECT employee_id, expire_date FROM employee_documents ORDER BY expire_date IS NULL, expire_date ASC') as $row) {
    $docCounts[$row['employee_id']] = ($docCounts[$row['employee_id']] ?? 0) + 1;

    if (!array_key_exists($row['employee_id'], $nearestExpireByEmployee)) {
        $nearestExpireByEmployee[$row['employee_id']] = $row['expire_date'];
    }
}

$spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Сводка по сотрудникам');

$headers = ['ФИО', 'Гражданство', 'Подразделение', 'Документов', 'Ближайший срок', 'Статус'];
$sheet->fromArray($headers, null, 'A1');
$sheet->getStyle('A1:F1')->getFont()->setBold(true);

$rowNumber = 2;

foreach ($employees as $employee) {
    $expireDate = $nearestExpireByEmployee[$employee['id']] ?? null;
    $status = documentStatus($expireDate);

    $sheet->fromArray([
        $employee['full_name'],
        $employee['citizenship'] ?? '',
        $employee['department'] ?? '',
        $docCounts[$employee['id']] ?? 0,
        $expireDate ?? '',
        $status['label'],
    ], null, 'A' . $rowNumber);

    $rowNumber++;
}

foreach (range('A', 'F') as $column) {
    $sheet->getColumnDimension($column)->setAutoSize(true);
}

$tmpPath = tempnam(sys_get_temp_dir(), 'xlsx');
$writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
$writer->save($tmpPath);

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="employees-report.xlsx"');
header('Content-Length: ' . filesize($tmpPath));
readfile($tmpPath);
unlink($tmpPath);
exit;
