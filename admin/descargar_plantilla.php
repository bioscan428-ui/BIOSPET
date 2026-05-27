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

// ========== ENCABEZADOS DE PRODUCTOS ==========
$encabezados = [
    'A1' => 'nombre',
    'B1' => 'descripcion',
    'C1' => 'codigo_barras',
    'D1' => 'id_categoria',
    'E1' => 'id_proveedor',
    'F1' => 'precio_compra',
    'G1' => 'precio_venta',
    'H1' => 'stock_actual',
    'I1' => 'stock_minimo',
    'J1' => 'unidad_medida',
    'K1' => 'ubicacion',
    'L1' => 'fecha_vencimiento'
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
$hoja->getStyle('A1:L1')->applyFromArray($headerStyle);

// ========== DATOS DE EJEMPLO ==========
$ejemplos = [
    [
        'Croqueta Premium para Perros',
        'Alimento balanceado para perros adultos, 2kg',
        'BIO001',
        1,
        1,
        350,
        450,
        50,
        10,
        'kg',
        'Estante A1',
        date('Y-m-d', strtotime('+1 year'))
    ],
    [
        'Juguete Pelota de Goma',
        'Pelota resistente para perros, ideal para jugar',
        'BIO002',
        2,
        2,
        25,
        49,
        100,
        20,
        'pieza',
        'Estante B2',
        ''
    ],
    [
        'Correa Nylon Resistente',
        'Correa para perros de 1.5 metros, color negro',
        'BIO003',
        3,
        1,
        35,
        69,
        30,
        5,
        'pieza',
        'Estante C3',
        ''
    ],
    [
        'Vacuna Triple Felina',
        'Vacuna contra enfermedades felinas',
        'BIO004',
        4,
        3,
        120,
        250,
        20,
        5,
        'pieza',
        'Refrigerador',
        date('Y-m-d', strtotime('+6 months'))
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
    $hoja->setCellValue('I' . $fila, $ejemplo[8]);
    $hoja->setCellValue('J' . $fila, $ejemplo[9]);
    $hoja->setCellValue('K' . $fila, $ejemplo[10]);
    $hoja->setCellValue('L' . $fila, $ejemplo[11]);
    $fila++;
}

// Estilo para datos de ejemplo
$exampleStyle = [
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F5F5F5']],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
];
$hoja->getStyle('A2:L' . ($fila - 1))->applyFromArray($exampleStyle);

// Ajustar ancho de columnas
foreach(range('A', 'L') as $col) {
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
$hojaInstrucciones->setCellValue('A6', '   - nombre');
$hojaInstrucciones->setCellValue('A7', '   - id_categoria');
$hojaInstrucciones->setCellValue('A8', '   - precio_venta');
$hojaInstrucciones->setCellValue('A9', '4. id_categoria: Use los IDs de la hoja "Categorias"');
$hojaInstrucciones->setCellValue('A10', '5. id_proveedor: Use los IDs de la hoja "Proveedores" (opcional)');
$hojaInstrucciones->setCellValue('A11', '6. Fecha de vencimiento debe estar en formato YYYY-MM-DD');
$hojaInstrucciones->setCellValue('A12', '7. Si no se especifica stock_minimo, se usará 5');
$hojaInstrucciones->setCellValue('A13', '8. Si no se especifica unidad_medida, se usará "pieza"');
$hojaInstrucciones->getColumnDimension('A')->setWidth(60);

// ========== HOJA DE CATEGORÍAS ==========
$hojaCategorias = $spreadsheet->createSheet();
$hojaCategorias->setTitle('Categorias');
$hojaCategorias->setCellValue('A1', 'ID');
$hojaCategorias->setCellValue('B1', 'Nombre de Categoría');
$hojaCategorias->getStyle('A1:B1')->getFont()->setBold(true);
$hojaCategorias->getStyle('A1:B1')->getFill()
    ->setFillType(Fill::FILL_SOLID)
    ->getStartColor()->setRGB('E68D0B');
$hojaCategorias->getStyle('A1:B1')->getFont()->getColor()->setRGB('FFFFFF');

// Obtener categorías de la BD
$sql_cats = "SELECT id, nombre FROM CATEGORIA_PRODUCTO WHERE activo = 1 ORDER BY id";
$cats = $conn->query($sql_cats);
$fila_cat = 2;
while($cat = $cats->fetch_assoc()) {
    $hojaCategorias->setCellValue('A' . $fila_cat, $cat['id']);
    $hojaCategorias->setCellValue('B' . $fila_cat, $cat['nombre']);
    $fila_cat++;
}

$hojaCategorias->getColumnDimension('A')->setWidth(10);
$hojaCategorias->getColumnDimension('B')->setWidth(35);

// ========== HOJA DE PROVEEDORES ==========
$hojaProveedores = $spreadsheet->createSheet();
$hojaProveedores->setTitle('Proveedores');
$hojaProveedores->setCellValue('A1', 'ID');
$hojaProveedores->setCellValue('B1', 'Nombre del Proveedor');
$hojaProveedores->getStyle('A1:B1')->getFont()->setBold(true);
$hojaProveedores->getStyle('A1:B1')->getFill()
    ->setFillType(Fill::FILL_SOLID)
    ->getStartColor()->setRGB('E68D0B');
$hojaProveedores->getStyle('A1:B1')->getFont()->getColor()->setRGB('FFFFFF');

// Obtener proveedores de la BD
$sql_provs = "SELECT id, nombre FROM PROVEEDOR WHERE activo = 1 ORDER BY nombre";
$provs = $conn->query($sql_provs);
$fila_prov = 2;
while($prov = $provs->fetch_assoc()) {
    $hojaProveedores->setCellValue('A' . $fila_prov, $prov['id']);
    $hojaProveedores->setCellValue('B' . $fila_prov, $prov['nombre']);
    $fila_prov++;
}

$hojaProveedores->getColumnDimension('A')->setWidth(10);
$hojaProveedores->getColumnDimension('B')->setWidth(40);

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