<?php
// admin/descargar_plantilla.php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

if (!in_array($_SESSION['rol'], ['super_admin', 'admin', 'caja'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../includes/conexion.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/biospet/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;

// Crear nuevo documento
$spreadsheet = new Spreadsheet();
$hoja = $spreadsheet->getActiveSheet();
$hoja->setTitle('Productos');

// ========== ENCABEZADOS SEGÚN TU EXCEL ==========
$encabezados = [
    'A1' => 'Codigo',
    'B1' => 'Descripcion',
    'C1' => 'Precio Costo',
    'D1' => 'Precio Venta',
    'E1' => 'Precio Mayoreo',
    'F1' => 'Inventario',
    'G1' => 'Inv. Minimo',
    'H1' => 'Departamento'
];

foreach ($encabezados as $celda => $valor) {
    $hoja->setCellValue($celda, $valor);
}

// Estilo para encabezados
$headerStyle = [
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E68D0B']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
];
$hoja->getStyle('A1:H1')->applyFromArray($headerStyle);

// ========== DATOS DE EJEMPLO ==========
$ejemplos = [
    [
        'BIO001',
        'Croqueta Premium para Perros 2kg',
        350,
        450,
        420,
        50,
        10,
        'Alimentos'
    ],
    [
        'BIO002',
        'Juguete Pelota de Goma',
        25,
        49,
        45,
        100,
        20,
        'Juguetes'
    ],
    [
        'BIO003',
        'Correa Nylon Resistente',
        35,
        69,
        60,
        30,
        5,
        'Accesorios'
    ],
    [
        'BIO004',
        'Vacuna Triple Felina',
        120,
        250,
        230,
        20,
        5,
        'Vacunas'
    ]
];

$fila = 2;
foreach ($ejemplos as $ejemplo) {
    $hoja->setCellValue('A' . $fila, $ejemplo[0]);
    $hoja->setCellValue('B' . $fila, $ejemplo[1]);
    $hoja->setCellValue('C' . $fila, $ejemplo[2]);
    $hoja->setCellValue('D' . $fila, $ejemplo[3]);
    $hoja->setCellValue('E' . $fila, $ejemplo[4]);
    $hoja->setCellValue('F' . $fila, $ejemplo[5]);
    $hoja->setCellValue('G' . $fila, $ejemplo[6]);
    $hoja->setCellValue('H' . $fila, $ejemplo[7]);
    $fila++;
}

// Estilo para datos de ejemplo
$exampleStyle = [
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F5F5F5']],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
];
$hoja->getStyle('A2:H' . ($fila - 1))->applyFromArray($exampleStyle);

// Ajustar ancho de columnas
foreach(range('A', 'H') as $col) {
    $hoja->getColumnDimension($col)->setAutoSize(true);
}

// ========== HOJA DE INSTRUCCIONES ==========
$hojaInstrucciones = $spreadsheet->createSheet();
$hojaInstrucciones->setTitle('Instrucciones');
$hojaInstrucciones->setCellValue('A1', 'INSTRUCCIONES PARA IMPORTAR PRODUCTOS');
$hojaInstrucciones->getStyle('A1')->getFont()->setBold(true)->setSize(14);
$hojaInstrucciones->setCellValue('A3', '1. Complete los datos en la hoja "Productos" a partir de la fila 2');
$hojaInstrucciones->setCellValue('A4', '2. No modifique los encabezados de las columnas');
$hojaInstrucciones->setCellValue('A5', '3. Los campos marcados con * son obligatorios:');
$hojaInstrucciones->setCellValue('A6', '   - Descripcion');
$hojaInstrucciones->setCellValue('A7', '   - Precio Venta');
$hojaInstrucciones->setCellValue('A8', '   - Departamento');
$hojaInstrucciones->setCellValue('A9', '4. El Departamento se usará para crear o asignar la categoría del producto');
$hojaInstrucciones->setCellValue('A10', '5. El código de barras debe ser único');
$hojaInstrucciones->setCellValue('A11', '6. El Precio Mayoreo es opcional y no se utiliza en la importación');
$hojaInstrucciones->getColumnDimension('A')->setWidth(60);

// ========== HOJA DE CATEGORÍAS (Departamentos existentes) ==========
$hojaCategorias = $spreadsheet->createSheet();
$hojaCategorias->setTitle('Categorias');
$hojaCategorias->setCellValue('A1', 'Departamento');
$hojaCategorias->setCellValue('B1', 'ID (solo referencia)');
$hojaCategorias->getStyle('A1:B1')->getFont()->setBold(true);
$hojaCategorias->getStyle('A1:B1')->getFill()
    ->setFillType(Fill::FILL_SOLID)
    ->getStartColor()->setRGB('E68D0B');
$hojaCategorias->getStyle('A1:B1')->getFont()->getColor()->setRGB('FFFFFF');

// Obtener categorías de la BD
$sql_cats = "SELECT id, nombre FROM CATEGORIA_PRODUCTO WHERE activo = 1 ORDER BY nombre";
$cats = $conn->query($sql_cats);
$fila_cat = 2;
while($cat = $cats->fetch_assoc()) {
    $hojaCategorias->setCellValue('A' . $fila_cat, $cat['nombre']);
    $hojaCategorias->setCellValue('B' . $fila_cat, $cat['id']);
    $fila_cat++;
}

$hojaCategorias->getColumnDimension('A')->setWidth(35);
$hojaCategorias->getColumnDimension('B')->setWidth(15);

// Seleccionar la hoja de productos al abrir
$spreadsheet->setActiveSheetIndex(0);

// Configurar cabeceras para descarga
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="plantilla_productos.xlsx"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
?>