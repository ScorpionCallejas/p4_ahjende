<?php
// Configuración de la base de datos
$host = "localhost";
$user = "root";
$password = "";
$dbname = "database_eventos";

// Crear conexión
$conn = new mysqli($host, $user, $password, $dbname);

// Verificar conexión
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Establecer el charset
$conn->set_charset("utf8");

// Función para obtener color según tipo de evento
function getEventColor($type)
{
    switch ($type) {
        case 'Administrativo':
            return '#3498db';
        case 'Admisiones':
            return '#9b59b6';
        case 'Academico':
            return '#e74c3c';
        default:
            return '#9b59b6';
    }
}

// Función para notificar cambios via WebSocket
function broadcastUpdate() {
    $server = "wss://socket.ahjende.com/broadcast";
    $channel = "events";
    $message = "update_events";
    
    $data = json_encode(array(
        'channel' => $channel,
        'message' => $message
    ));
    
    $ch = curl_init($server);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/json'
    ));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 2);
    
    $response = curl_exec($ch);
    if(curl_errno($ch)) {
        error_log('Error en broadcastUpdate: ' . curl_error($ch));
    }
    curl_close($ch);
}

// Procesar solicitudes AJAX
if (isset($_GET['action'])) {
    header('Content-Type: application/json');

    switch ($_GET['action']) {
        case 'get_events':
            $start = isset($_GET['start']) ? $conn->real_escape_string($_GET['start']) : '';
            $end = isset($_GET['end']) ? $conn->real_escape_string($_GET['end']) : '';

            $query = "SELECT e.id_eve, e.nom_eve, DATE(e.fec_ini) as fec_ini, DATE(e.fec_fin) as fec_fin, 
                             e.tip_eve, e.est_eve, e.nom_eje, e.nom_pla,
                             ej.nom_eje as nombre_ejecutivo, 
                             p.nom_pla as nombre_plantel
                      FROM eventos e
                      LEFT JOIN database_ejecutivos.ejecutivo ej ON e.nom_eje = ej.nom_eje
                      LEFT JOIN database_plantel.plantel p ON e.nom_pla = p.nom_pla
                      WHERE 1=1";

            if (!empty($start) && !empty($end)) {
                $query .= " AND (
                    (DATE(e.fec_ini) BETWEEN '$start' AND '$end') OR
                    (DATE(e.fec_fin) BETWEEN '$start' AND '$end') OR
                    (DATE(e.fec_ini) <= '$start' AND DATE(e.fec_fin) >= '$end')
                )";
            }

            $result = $conn->query($query);

            if (!$result) {
                echo json_encode(array('error' => $conn->error));
                break;
            }

            $events = array();
            $lastUpdate = time();

            while ($row = $result->fetch_assoc()) {
                $events[] = array(
                    'id' => $row['id_eve'],
                    'title' => $row['nom_eve'],
                    'start' => $row['fec_ini'],
                    'end' => $row['fec_fin'],
                    'color' => getEventColor($row['tip_eve']),
                    'tip_eve' => $row['tip_eve'],
                    'est_eve' => $row['est_eve'],
                    'ejecutivo' => $row['nombre_ejecutivo'] ? $row['nombre_ejecutivo'] : $row['nom_eje'],
                    'plantel' => $row['nombre_plantel'] ? $row['nombre_plantel'] : $row['nom_pla']
                );
            }

            echo json_encode(array('events' => $events, 'last_update' => $lastUpdate));
            break;

        case 'get_date_events':
            $date = isset($_GET['date']) ? $conn->real_escape_string($_GET['date']) : '';

            if (empty($date)) {
                echo json_encode(array('error' => 'Fecha no especificada'));
                break;
            }

            $query = "SELECT e.id_eve, e.nom_eve, DATE(e.fec_ini) as fec_ini, DATE(e.fec_fin) as fec_fin, 
                             e.tip_eve, e.est_eve, e.nom_eje, e.nom_pla,
                             ej.nom_eje as nombre_ejecutivo, 
                             p.nom_pla as nombre_plantel
                      FROM eventos e
                      LEFT JOIN database_ejecutivos.ejecutivo ej ON e.nom_eje = ej.nom_eje
                      LEFT JOIN database_plantel.plantel p ON e.nom_pla = p.nom_pla
                      WHERE (
                          DATE(e.fec_ini) = '$date' OR
                          DATE(e.fec_fin) = '$date' OR
                          (DATE(e.fec_ini) <= '$date' AND DATE(e.fec_fin) >= '$date')
                      )
                      ORDER BY e.fec_ini";

            $result = $conn->query($query);

            if (!$result) {
                echo json_encode(array('error' => $conn->error));
                break;
            }

            $events = array();

            while ($row = $result->fetch_assoc()) {
                $events[] = array(
                    'id' => $row['id_eve'],
                    'title' => $row['nom_eve'],
                    'start' => $row['fec_ini'],
                    'end' => $row['fec_fin'],
                    'type' => $row['tip_eve'],
                    'status' => $row['est_eve'],
                    'ejecutivo' => $row['nombre_ejecutivo'] ? $row['nombre_ejecutivo'] : $row['nom_eje'],
                    'plantel' => $row['nombre_plantel'] ? $row['nombre_plantel'] : $row['nom_pla']
                );
            }

            echo json_encode($events);
            break;

        case 'update_event_status':
            $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
            $status = isset($_GET['status']) ? $conn->real_escape_string($_GET['status']) : '';

            if ($id <= 0 || !in_array($status, array('Pendiente', 'Completado'))) {
                echo json_encode(array('status' => 'error', 'message' => 'Parámetros inválidos'));
                break;
            }

            $sql = "UPDATE eventos SET est_eve = '$status' WHERE id_eve = $id";

            if ($conn->query($sql)) {
                broadcastUpdate();
                echo json_encode(array('status' => 'success', 'last_update' => time()));
            } else {
                echo json_encode(array('status' => 'error', 'message' => $conn->error));
            }
            break;

        case 'delete_event':
            $id = isset($_GET['id']) ? intval($_GET['id']) : 0;

            if ($id <= 0) {
                echo json_encode(array('status' => 'error', 'message' => 'ID inválido'));
                break;
            }

            $sql = "DELETE FROM eventos WHERE id_eve = $id";

            if ($conn->query($sql)) {
                broadcastUpdate();
                echo json_encode(array('status' => 'success', 'last_update' => time()));
            } else {
                echo json_encode(array('status' => 'error', 'message' => $conn->error));
            }
            break;

        case 'update_event_dates':
            $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
            $start = isset($_GET['start']) ? $conn->real_escape_string($_GET['start']) : '';
            $end = isset($_GET['end']) ? $conn->real_escape_string($_GET['end']) : '';

            if ($id <= 0 || empty($start)) {
                echo json_encode(array('status' => 'error', 'message' => 'Parámetros inválidos'));
                break;
            }

            $sql = "UPDATE eventos SET fec_ini = '$start'";
            if (!empty($end)) {
                $sql .= ", fec_fin = '$end'";
            }
            $sql .= " WHERE id_eve = $id";

            if ($conn->query($sql)) {
                broadcastUpdate();
                echo json_encode(array('status' => 'success', 'last_update' => time()));
            } else {
                echo json_encode(array('status' => 'error', 'message' => $conn->error));
            }
            break;
    }

    $conn->close();
    exit;
}

// Procesar creación de eventos
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'add_event') {
    header('Content-Type: application/json');

    $required = array('nom_eve', 'fec_ini', 'fec_fin', 'tip_eve');
    $missing = array();

    foreach ($required as $field) {
        if (empty($_POST[$field])) {
            $missing[] = $field;
        }
    }

    if (!empty($missing)) {
        echo json_encode(array(
            'status' => 'error',
            'message' => 'Campos requeridos faltantes: ' . implode(', ', $missing)
        ));
        $conn->close();
        exit;
    }

    $nom_eve = $conn->real_escape_string($_POST['nom_eve']);
    $fec_ini = $conn->real_escape_string($_POST['fec_ini']);
    $fec_fin = $conn->real_escape_string($_POST['fec_fin']);
    $tip_eve = $conn->real_escape_string($_POST['tip_eve']);

    // Valores predeterminados
    $nom_eje = "Raul Callejas";
    $nom_pla = "Ecatepec";

    $sql = "INSERT INTO eventos (nom_eve, fec_ini, fec_fin, tip_eve, nom_eje, nom_pla, est_eve) 
            VALUES ('$nom_eve', '$fec_ini', '$fec_fin', '$tip_eve', '$nom_eje', '$nom_pla', 'Pendiente')";

    if ($conn->query($sql)) {
        broadcastUpdate();
        echo json_encode(array('status' => 'success', 'last_update' => time()));
    } else {
        echo json_encode(array(
            'status' => 'error',
            'message' => 'Error en la base de datos: ' . $conn->error
        ));
    }

    $conn->close();
    exit;
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendario de Eventos</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- FullCalendar CSS v3 -->
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@3.10.2/dist/fullcalendar.min.css" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Librerías para exportar a PDF -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/1.5.3/jspdf.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>

    <style>
        :root {
            --primary-color: #4361ee;
            --secondary-color: #3f37c9;
            --accent-color: #4895ef;
            --light-color: #f8f9fa;
            --dark-color: #212529;
            --success-color: #4cc9f0;
            --danger-color: #f72585;
            --warning-color: #f8961e;
            --info-color: #4895ef;
        }

        body {
            padding: 20px;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f7fa;
        }

        #calendar-container {
            max-width: 1200px;
            margin: 0 auto;
            background-color: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        .fc-toolbar {
            padding: 15px;
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            margin-bottom: 20px !important;
        }

        .fc-toolbar h2 {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--dark-color);
            margin: 0;
        }

        .fc-button {
            background-color: white !important;
            color: var(--primary-color) !important;
            border: 1px solid #e0e0e0 !important;
            border-radius: 8px !important;
            padding: 8px 15px !important;
            font-weight: 500 !important;
            transition: all 0.3s ease !important;
            box-shadow: none !important;
            text-transform: capitalize !important;
        }

        .fc-button:hover {
            background-color: var(--primary-color) !important;
            color: white !important;
            border-color: var(--primary-color) !important;
        }

        .fc-icon {
            color: var(--primary-color) !important;
            font-weight: bold !important;
            font-size: 1.3em !important;
            line-height: 0.8 !important;
            vertical-align: middle !important;
            text-shadow: none !important;
        }

        .fc-button:hover .fc-icon {
            color: white !important;
        }

        .fc-prev-button,
        .fc-next-button {
            min-width: 40px !important;
            padding: 8px 12px !important;
        }

        .fc-button:active,
        .fc-button:focus {
            box-shadow: 0 0 0 0.25rem rgba(67, 97, 238, 0.3) !important;
        }

        .fc-button-primary:not(:disabled).fc-button-active {
            background-color: var(--primary-color) !important;
            color: white !important;
            border-color: var(--primary-color) !important;
        }

        .fc-today-button {
            background-color: var(--primary-color) !important;
            color: white !important;
            border-color: var(--primary-color) !important;
        }

        .fc-exportPdf-button {
            background-color: var(--primary-color) !important;
            color: white !important;
            border: 1px solid var(--primary-color) !important;
            border-radius: 8px !important;
            padding: 8px 15px !important;
            font-weight: 500 !important;
            transition: all 0.3s ease !important;
            box-shadow: none !important;
            text-transform: capitalize !important;
            margin-left: 10px !important;
        }

        .fc-exportPdf-button:hover {
            background-color: var(--secondary-color) !important;
            border-color: var(--secondary-color) !important;
        }

        .fc-event {
            border: none !important;
            border-radius: 6px !important;
            padding: 5px 8px !important;
            font-size: 0.9rem;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s ease;
            color: white !important;
            text-shadow: 0 1px 1px rgba(0, 0, 0, 0.3);
            cursor: move;
            margin-bottom: 5px !important;
        }

        .fc-event:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
        }

        .fc-day-grid-event .fc-content {
            white-space: normal !important;
        }

        .fc-day:hover {
            background-color: rgba(67, 97, 238, 0.05);
            cursor: pointer;
        }

        .fc-day-header {
            background-color: #f8f9fa;
            color: var(--dark-color);
            font-weight: 500;
            padding: 10px 0;
        }

        .completed-event {
            opacity: 0.7;
            text-decoration: line-through;
        }

        .modal-header {
            border-bottom: none;
            padding-bottom: 0;
        }

        .modal-title {
            font-weight: 600;
            color: var(--dark-color);
        }

        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .btn-primary:hover {
            background-color: var(--secondary-color);
            border-color: var(--secondary-color);
        }

        .btn-delete {
            background-color: var(--danger-color);
            border-color: var(--danger-color);
        }

        .btn-delete:hover {
            background-color: #d11465;
            border-color: #d11465;
        }

        .form-control,
        .form-select {
            border-radius: 8px;
            padding: 10px 15px;
            border: 1px solid #e0e0e0;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(67, 97, 238, 0.2);
        }

        .switch {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 24px;
        }

        .switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 24px;
        }

        .slider:before {
            position: absolute;
            content: "";
            height: 16px;
            width: 16px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }

        input:checked+.slider {
            background-color: var(--success-color);
        }

        input:checked+.slider:before {
            transform: translateX(26px);
        }

        .btn i {
            margin-right: 8px;
        }

        .event-type {
            color: #fff;
            padding: 10px;
        }

        .btn-delete {
            color: #fff;
        }

        .fc-event .fc-resizer {
            position: absolute;
            right: 0;
            top: 0;
            bottom: 0;
            width: 8px;
            cursor: e-resize;
            z-index: 1001 !important;
        }

        .fc-event .fc-resizer:after {
            content: "≡";
            position: absolute;
            right: 2px;
            top: 50%;
            transform: translateY(-50%);
            color: white;
            font-weight: bold;
            font-size: 12px;
        }

        .fc-event .fc-resizer.fc-start-resizer {
            display: none !important;
        }

        #custom-event-tooltip {
            position: fixed;
            z-index: 9999;
            background: rgba(0, 0, 0, 0.9);
            color: white;
            padding: 10px 15px;
            border-radius: 6px;
            font-size: 13px;
            max-width: 280px;
            pointer-events: none;
            display: none;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
            transition: opacity 0.2s;
        }

        #custom-event-tooltip .tooltip-header {
            font-weight: bold;
            margin-bottom: 5px;
            color: #fff;
            font-size: 14px;
        }

        #custom-event-tooltip .tooltip-content {
            line-height: 1.5;
        }

        #custom-event-tooltip .tooltip-content p {
            margin-bottom: 3px;
        }

        #list-view-container {
            max-width: 1200px;
            margin: 20px auto;
            background-color: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            display: none;
        }

        .list-view-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }

        .list-view-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--dark-color);
            margin: 0;
        }

        .list-view-date {
            font-size: 1.2rem;
            color: var(--dark-color);
            font-weight: 500;
        }

        .list-view-events {
            margin-top: 15px;
        }

        .list-view-day {
            margin-bottom: 30px;
        }

        .list-view-day-header {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 15px;
            padding-bottom: 5px;
            border-bottom: 2px solid var(--primary-color);
        }

        .list-view-event {
            background-color: #f9f9f9;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 10px;
            border-left: 4px solid var(--primary-color);
            transition: all 0.2s ease;
        }

        .list-view-event:hover {
            transform: translateX(5px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .list-view-event-title {
            font-weight: 600;
            font-size: 1.1rem;
            margin-bottom: 5px;
        }

        .list-view-event-details {
            display: flex;
            flex-wrap: wrap;
            margin-top: 10px;
        }

        .list-view-event-detail {
            flex: 1 1 200px;
            margin-bottom: 5px;
        }

        .list-view-event-detail i {
            margin-right: 8px;
            color: var(--primary-color);
        }

        .list-view-event-type {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: 500;
            color: white;
            margin-right: 10px;
        }

        .list-view-event-status {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: 500;
            background-color: #f0f0f0;
        }

        .status-completado {
            background-color: #2ecc71;
            color: white;
            padding: 5px;
        }

        .status-pendiente {
            padding: 5px;
            background-color: #f39c12;
            color: white;
        }

        .view-tabs {
            display: flex;
            justify-content: center;
            margin-bottom: 20px;
        }

        .view-tab {
            padding: 10px 20px;
            background-color: white;
            border: 1px solid #e0e0e0;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .view-tab:first-child {
            border-radius: 8px 0 0 8px;
            border-right: none;
        }

        .view-tab:last-child {
            border-radius: 0 8px 8px 0;
            border-left: none;
        }

        .view-tab.active {
            background-color: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }

        .fc-day-grid-container {
            overflow: visible !important;
        }

        .fc-day-grid {
            overflow: visible !important;
        }

        .fc-row {
            overflow: visible !important;
        }

        .fc-content-skeleton {
            overflow: visible !important;
        }
    </style>
</head>

<body>
    <div class="container">
        <h1 class="text-center mb-4" style="color: var(--primary-color); font-weight: 700;">Calendario de Eventos</h1>

        <!-- Tabs para cambiar entre vistas -->
        <div class="view-tabs">
            <div class="view-tab active" data-view="calendar">Vista de Calendario</div>
            <div class="view-tab" data-view="list">Vista de Lista</div>
        </div>

        <!-- Contenedor del calendario -->
        <div id="calendar-container">
            <div id="calendar"></div>
        </div>

        <!-- Contenedor de la vista de lista -->
        <div id="list-view-container">
            <div class="list-view-header">
                <h2 class="list-view-title">Eventos</h2>
                <div class="list-view-date" id="list-view-date-range"></div>
            </div>
            <div class="list-view-events" id="list-view-events">
                <!-- Los eventos se cargarán aquí -->
            </div>
        </div>
    </div>

    <!-- Tooltip personalizado -->
    <div id="custom-event-tooltip">
        <div class="tooltip-header"></div>
        <div class="tooltip-content"></div>
    </div>

    <!-- Modal para agregar eventos -->
    <div class="modal fade" id="addEventModal" tabindex="-1" aria-labelledby="addEventModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-calendar-plus"></i> Nuevo Evento</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="eventForm">
                        <div class="mb-3">
                            <label for="nom_eve" class="form-label">Título del Evento*</label>
                            <input type="text" class="form-control" id="nom_eve" name="nom_eve" required placeholder="Ej: Reunión de socios...">
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="fec_ini" class="form-label">Fecha de Inicio*</label>
                                <input type="date" class="form-control" id="fec_ini" name="fec_ini" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="fec_fin" class="form-label">Fecha de Fin*</label>
                                <input type="date" class="form-control" id="fec_fin" name="fec_fin" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="tip_eve" class="form-label">Área*</label>
                            <select class="form-select" id="tip_eve" name="tip_eve" required>
                                <option value="">Seleccione un área</option>
                                <option value="Administrativo">Administrativo</option>
                                <option value="Admisiones">Admisiones</option>
                                <option value="Academico">Académico</option>
                            </select>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fas fa-times"></i> Cancelar</button>
                    <button type="button" class="btn btn-primary" id="saveEvent"><i class="fas fa-save"></i> Guardar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para detalles del evento -->
    <div class="modal fade" id="eventDetailModal" tabindex="-1" aria-labelledby="eventDetailModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-calendar-alt"></i> Detalles del Evento</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="eventDetailContent">
                    <!-- Contenido dinámico -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-delete" id="deleteEvent"><i class="fas fa-trash-alt"></i> Eliminar</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fas fa-times"></i> Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de confirmación para eliminar -->
    <div class="modal fade" id="confirmDeleteModal" tabindex="-1" aria-labelledby="confirmDeleteModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-exclamation-triangle"></i> Confirmar Eliminación</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>¿Estás seguro de que deseas eliminar este evento? Esta acción no se puede deshacer.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fas fa-times"></i> Cancelar</button>
                    <button type="button" class="btn btn-danger" id="confirmDelete"><i class="fas fa-trash-alt"></i> Eliminar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- jQuery -->
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>

    <!-- Bootstrap JS Bundle con Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- FullCalendar JS v3 -->
    <script src="https://cdn.jsdelivr.net/npm/moment@2.29.1/moment.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@3.10.2/dist/fullcalendar.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@3.10.2/dist/locale/es.js"></script>

    <script>
        $(document).ready(function() {
            var currentEventId = null;
            var tooltipTimeout = null;
            var tooltipVisible = false;
            var currentView = 'calendar';
            var lastUpdateTime = 0;
            var calendarRefreshInterval = 3000; // 3 segundos
            var forceRefresh = false;
            
            // WebSocket para actualizaciones en tiempo real
            var socket;

function connectWebSocket() {
    try {
        // Prueba con wss:// primero, luego ws:// como fallback
        socket = new WebSocket('wss://socket.ahjende.com/wss/?encoding=text');
        
        socket.onopen = function() {
            console.log('Conexión WebSocket establecida');
            // Reinicia el intervalo de polling si estaba activo
            clearInterval(pollingInterval);
        };
        
        socket.onmessage = function(event) {
            if (event.data === 'update_events') {
                forceRefresh = true;
                refreshCalendar();
            }
        };
        
        socket.onerror = function(error) {
            console.error('Error en WebSocket:', error);
            startPolling();
        };
        
        socket.onclose = function(event) {
            console.log('Conexión WebSocket cerrada:', event.reason);
            // Intenta reconectar después de 5 segundos
            setTimeout(connectWebSocket, 1000);
        };
    } catch (e) {
        console.error('Error al crear WebSocket:', e);
        startPolling();
    }
}

// Inicia la conexión al cargar la página
connectWebSocket();
            
            socket.onopen = function() {
                console.log('Conexión WebSocket establecida');
                startPolling();
            };
            
            socket.onmessage = function(event) {
                if (event.data === 'update_events') {
                    forceRefresh = true;
                    refreshCalendar();
                }
            };
            
            socket.onerror = function(error) {
                console.error('Error en WebSocket:', error);
                startPolling();
            };
            
            socket.onclose = function(event) {
                console.log('Conexión WebSocket cerrada:', event.reason);
                startPolling();
            };
            
            function startPolling() {
                setInterval(function() {
                    checkForUpdates();
                }, calendarRefreshInterval);
            }
            
            function checkForUpdates() {
                $.ajax({
                    url: 'eventos.php',
                    method: 'GET',
                    data: {
                        action: 'get_events',
                        last_update: lastUpdateTime
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.last_update > lastUpdateTime || forceRefresh) {
                            lastUpdateTime = response.last_update;
                            forceRefresh = false;
                            refreshCalendar();
                        }
                    },
                    error: function() {
                        console.log('Error al verificar actualizaciones');
                    }
                });
            }
            
            function refreshCalendar() {
                $('#calendar').fullCalendar('refetchEvents');
                if (currentView === 'list') {
                    var view = $('#calendar').fullCalendar('getView');
                    loadListEvents(view.start, view.end);
                }
                lastUpdateTime = Date.now();
            }

            var calendar = $('#calendar').fullCalendar({
                timeZone: 'UTC',
                defaultView: 'month',
                header: {
                    left: 'prev,next today',
                    center: 'title',
                    right: ''
                },
                buttonIcons: {
                    prev: 'chevron-left',
                    next: 'chevron-right'
                },
                views: {
                    month: {
                        titleFormat: 'MMMM YYYY'
                    }
                },
                locale: 'es',
                selectable: false,
                editable: true,
                eventDurationEditable: true,
                events: function(start, end, timezone, callback) {
                    $.ajax({
                        url: 'eventos.php',
                        method: 'GET',
                        data: {
                            action: 'get_events',
                            start: start.format('YYYY-MM-DD'),
                            end: end.format('YYYY-MM-DD')
                        },
                        dataType: 'json',
                        success: function(response) {
                            callback(response.events);
                            lastUpdateTime = response.last_update;
                        },
                        error: function() {
                            alert('Error al cargar los eventos');
                        }
                    });
                },
                dayClick: function(date, jsEvent, view) {
                    if (!$(jsEvent.target).hasClass('fc-event')) {
                        $('#eventForm')[0].reset();
                        $('#fec_ini').val(date.format('YYYY-MM-DD'));
                        $('#fec_fin').val(date.format('YYYY-MM-DD'));
                        $('#addEventModal').modal('show');
                    }
                    hideTooltip();
                },
                eventClick: function(calEvent, jsEvent, view) {
                    currentEventId = calEvent.id;
                    showEventDetails(calEvent);
                    $('#eventDetailModal').modal('show');
                    hideTooltip();
                },
                eventRender: function(event, element) {
                    element.on('mouseenter', function(e) {
                        showTooltip(event, $(this), e);
                    });

                    element.on('mouseleave', function() {
                        startHideTooltip();
                    });

                    if (event.est_eve === 'Completado') {
                        element.addClass('completed-event');
                    }
                },
                eventDrop: function(event, delta, revertFunc, jsEvent, ui, view) {
                    updateEventDates(event);
                    hideTooltip();
                },
                eventResize: function(event, delta, revertFunc, jsEvent, ui, view) {
                    updateEventDates(event);
                    hideTooltip();
                },
                eventAfterAllRender: function(view) {
                    hideTooltip();
                },
                viewRender: function(view, element) {
                    updateListViewDateRange(view.start, view.end);
                    if (currentView === 'list') {
                        loadListEvents(view.start, view.end);
                    }
                }
            });

            var exportButton = $('<button class="fc-exportPdf-button fc-button fc-state-default fc-corner-left fc-corner-right">Exportar a PDF</button>');
            exportButton.click(function() {
                exportCalendarToPDF();
            });
            $('.fc-right').prepend(exportButton);

            function exportCalendarToPDF() {
                var loadingAlert = $('<div class="alert alert-info" style="position: fixed; top: 20px; left: 50%; transform: translateX(-50%); z-index: 9999;">Generando PDF, por favor espere...</div>');
                $('body').append(loadingAlert);

                var calendarEl = document.getElementById('calendar-container');
                var options = {
                    scale: 2,
                    useCORS: true,
                    allowTaint: true,
                    scrollX: 0,
                    scrollY: -window.scrollY,
                    windowWidth: document.documentElement.offsetWidth,
                    windowHeight: document.documentElement.offsetHeight
                };

                html2canvas(calendarEl, options).then(function(canvas) {
                    var pdf = new jsPDF('landscape');
                    var imgData = canvas.toDataURL('image/png');
                    var imgWidth = pdf.internal.pageSize.getWidth();
                    var imgHeight = (canvas.height * imgWidth) / canvas.width;
                    pdf.addImage(imgData, 'PNG', 0, 0, imgWidth, imgHeight);
                    pdf.save('calendario-' + new Date().toISOString().slice(0, 10) + '.pdf');
                    loadingAlert.remove();
                }).catch(function(error) {
                    console.error('Error al generar PDF:', error);
                    loadingAlert.remove();
                    alert('Error al generar el PDF. Por favor intente nuevamente.');
                });
            }

            $('.view-tab').click(function() {
                var view = $(this).data('view');
                $('.view-tab').removeClass('active');
                $(this).addClass('active');

                if (view === 'calendar') {
                    $('#calendar-container').show();
                    $('#list-view-container').hide();
                    currentView = 'calendar';
                } else {
                    $('#calendar-container').hide();
                    $('#list-view-container').show();
                    currentView = 'list';
                    var view = $('#calendar').fullCalendar('getView');
                    updateListViewDateRange(view.start, view.end);
                    loadListEvents(view.start, view.end);
                }
            });

            function loadListEvents(start, end) {
                var startStr = start.format('YYYY-MM-DD');
                var endStr = end.format('YYYY-MM-DD');

                $.ajax({
                    url: 'eventos.php',
                    method: 'GET',
                    data: {
                        action: 'get_events',
                        start: startStr,
                        end: endStr
                    },
                    dataType: 'json',
                    success: function(response) {
                        renderListEvents(response.events);
                        lastUpdateTime = response.last_update;
                    },
                    error: function() {
                        alert('Error al cargar los eventos');
                    }
                });
            }

            function renderListEvents(events) {
                var container = $('#list-view-events');
                container.empty();

                if (events.length === 0) {
                    container.html('<p>No hay eventos en este rango de fechas.</p>');
                    return;
                }

                var eventsByDay = {};
                events.forEach(function(event) {
                    var startDate = moment(event.start).format('YYYY-MM-DD');
                    var endDate = moment(event.end || event.start).format('YYYY-MM-DD');
                    var currentDate = moment(startDate);
                    var lastDate = moment(endDate);

                    while (currentDate <= lastDate) {
                        var dateKey = currentDate.format('YYYY-MM-DD');
                        if (!eventsByDay[dateKey]) {
                            eventsByDay[dateKey] = [];
                        }
                        eventsByDay[dateKey].push(event);
                        currentDate.add(1, 'days');
                    }
                });

                var sortedDays = Object.keys(eventsByDay).sort();
                sortedDays.forEach(function(day) {
                    var dayEvents = eventsByDay[day];
                    var dayMoment = moment(day);
                    var dayHeader = $('<div class="list-view-day-header"></div>');
                    dayHeader.text(dayMoment.format('dddd, D [de] MMMM [de] YYYY').toUpperCase());
                    var dayContainer = $('<div class="list-view-day"></div>');
                    dayContainer.append(dayHeader);
                    dayEvents.sort(function(a, b) {
                        return moment(a.start).diff(moment(b.start));
                    });

                    dayEvents.forEach(function(event) {
                        var eventElement = $('<div class="list-view-event"></div>');
                        var titleElement = $('<div class="list-view-event-title"></div>');
                        var typeElement = $('<span class="list-view-event-type"></span>');
                        typeElement.text(event.tip_eve);
                        typeElement.css('background-color', getEventColor(event.tip_eve));
                        titleElement.append(typeElement);
                        titleElement.append(document.createTextNode(' ' + event.title));
                        var statusElement = $('<span class="list-view-event-status"></span>');
                        statusElement.text(event.est_eve || 'Pendiente');
                        statusElement.addClass(event.est_eve === 'Completado' ? 'status-completado' : 'status-pendiente');
                        titleElement.append(' ');
                        titleElement.append(statusElement);
                        eventElement.append(titleElement);
                        var detailsElement = $('<div class="list-view-event-details"></div>');
                        var startDate = moment(event.start);
                        var endDate = moment(event.end || event.start);

                        if (startDate.format('YYYY-MM-DD') === endDate.format('YYYY-MM-DD')) {
                            var timeElement = $('<div class="list-view-event-detail"></div>');
                            timeElement.html('<i class="fas fa-clock"></i> ' + startDate.format('HH:mm') + ' - ' + endDate.format('HH:mm'));
                            detailsElement.append(timeElement);
                        } else {
                            var dateElement = $('<div class="list-view-event-detail"></div>');
                            dateElement.html('<i class="fas fa-calendar-day"></i> ' + startDate.format('DD/MM/YYYY') + ' - ' + endDate.format('DD/MM/YYYY'));
                            detailsElement.append(dateElement);
                        }

                        var execElement = $('<div class="list-view-event-detail"></div>');
                        execElement.html('<i class="fas fa-user-tie"></i> ' + (event.ejecutivo || 'Raul Callejas'));
                        detailsElement.append(execElement);
                        var plantelElement = $('<div class="list-view-event-detail"></div>');
                        plantelElement.html('<i class="fas fa-school"></i> ' + (event.plantel || 'Ecatepec'));
                        detailsElement.append(plantelElement);
                        eventElement.append(detailsElement);
                        eventElement.click(function() {
                            currentEventId = event.id;
                            showEventDetails(event);
                            $('#eventDetailModal').modal('show');
                        });
                        dayContainer.append(eventElement);
                    });
                    container.append(dayContainer);
                });
            }

            function updateListViewDateRange(start, end) {
                var startMoment = moment(start);
                var endMoment = moment(end).subtract(1, 'day');
                var rangeText = '';

                if (startMoment.format('MMMM YYYY') === endMoment.format('MMMM YYYY')) {
                    rangeText = startMoment.format('MMMM YYYY');
                } else if (startMoment.format('YYYY') === endMoment.format('YYYY')) {
                    rangeText = startMoment.format('MMMM') + ' - ' + endMoment.format('MMMM YYYY');
                } else {
                    rangeText = startMoment.format('MMMM YYYY') + ' - ' + endMoment.format('MMMM YYYY');
                }

                $('#list-view-date-range').text(rangeText);
            }

            function showTooltip(event, element, e) {
                clearTimeout(tooltipTimeout);
                var tooltip = $('#custom-event-tooltip');
                tooltip.find('.tooltip-header').text(event.title);
                tooltip.find('.tooltip-content').html(
                    '<p><strong>Área:</strong> ' + event.tip_eve + '</p>' +
                    '<p><strong>Inicio:</strong> ' + moment(event.start).format('DD/MM/YYYY') + '</p>' +
                    (event.end ? '<p><strong>Fin:</strong> ' + moment(event.end).format('DD/MM/YYYY') + '</p>' : '') +
                    '<p><strong>Estado:</strong> ' + (event.est_eve || 'Pendiente') + '</p>' +
                    '<p><strong>Ejecutivo:</strong> ' + (event.ejecutivo || 'Raul Callejas') + '</p>' +
                    '<p><strong>Plantel:</strong> ' + (event.plantel || 'Ecatepec') + '</p>'
                );

                var elementRect = element[0].getBoundingClientRect();
                var tooltipRect = tooltip[0].getBoundingClientRect();
                var left = elementRect.left + (elementRect.width / 2) - (tooltipRect.width / 2);
                var top = elementRect.top - tooltipRect.height - 5;
                left = Math.max(5, Math.min(left, window.innerWidth - tooltipRect.width - 5));
                top = Math.max(5, top);
                tooltip.css({
                    'display': 'block',
                    'left': '0',
                    'top': '0',
                    'transform': 'translate(' + left + 'px, ' + top + 'px)',
                    'opacity': '1'
                });
                tooltipVisible = true;
            }

            function startHideTooltip() {
                clearTimeout(tooltipTimeout);
                tooltipTimeout = setTimeout(function() {
                    hideTooltip();
                }, 200);
            }

            function hideTooltip() {
                $('#custom-event-tooltip').hide();
                tooltipVisible = false;
            }

            function updateEventDates(event) {
                var start = moment(event.start).format('YYYY-MM-DD');
                var end = event.end ? moment(event.end).format('YYYY-MM-DD') : null;

                $.ajax({
                    url: 'eventos.php',
                    method: 'GET',
                    data: {
                        action: 'update_event_dates',
                        id: event.id,
                        start: start,
                        end: end
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.status !== 'success') {
                            $('#calendar').fullCalendar('refetchEvents');
                        } else {
                            broadcastUpdate();
                        }
                    },
                    error: function() {
                        $('#calendar').fullCalendar('refetchEvents');
                    }
                });
            }

            function showEventDetails(event) {
                var modalContent = $('#eventDetailContent');
                currentEventId = event.id;
                var color = getEventColor(event.tip_eve);
                var startDate = moment(event.start);
                var endDate = moment(event.end || event.start);
                var startDateStr = startDate.format('DD/MM/YYYY');
                var endDateStr = endDate.format('DD/MM/YYYY');
                var dateRange = '';

                if (startDateStr !== endDateStr) {
                    dateRange = '<div class="event-time mb-2">' +
                        '<i class="fas fa-calendar-day"></i> <strong>Desde:</strong> ' + startDateStr + '<br>' +
                        '<i class="fas fa-calendar-day"></i> <strong>Hasta:</strong> ' + endDateStr +
                        '</div>';
                } else {
                    dateRange = '<div class="event-time mb-2">' +
                        '<i class="fas fa-calendar-day"></i> <strong>Fecha:</strong> ' + startDateStr +
                        '</div>';
                }

                var completedClass = event.est_eve === 'Completado' ? 'completed-event' : '';
                var html = '' +
                    '<div class="event-details ' + completedClass + '" style="border-left-color: ' + color + '">' +
                    '<div class="event-type" style="background-color: ' + color + '">' +
                    '<i class="fas fa-tag"></i> ' + event.tip_eve +
                    '</div>' +
                    '<h5 class="mt-3"> ' + event.title + '</h5>' +
                    dateRange +
                    '<div class="row mt-3">' +
                    '<div class="col-md-6">' +
                    '<p><i class="fas fa-user-tie"></i> <strong>Ejecutivo:</strong> ' + (event.ejecutivo || 'Raul Callejas') + '</p>' +
                    '</div>' +
                    '<div class="col-md-6">' +
                    '<p><i class="fas fa-school"></i> <strong>Plantel:</strong> ' + (event.plantel || 'Ecatepec') + '</p>' +
                    '</div>' +
                    '</div>' +
                    '<div class="status-switch mt-4">' +
                    '<label><i class="fas fa-toggle-on"></i> Estado:</label>' +
                    '<label class="switch ms-2">' +
                    '<input type="checkbox" class="status-toggle" data-event-id="' + event.id + '" ' + (event.est_eve === 'Completado' ? 'checked' : '') + '>' +
                    '<span class="slider"></span>' +
                    '</label>' +
                    '<span class="status-text ms-2 ' + (event.est_eve === 'Completado' ? 'status-completado' : 'status-pendiente') + '">' +
                    (event.est_eve || 'Pendiente') +
                    '</span>' +
                    '</div>' +
                    '</div>';

                modalContent.html(html);
                $('.status-toggle').change(function() {
                    var eventId = $(this).data('event-id');
                    var newStatus = $(this).is(':checked') ? 'Completado' : 'Pendiente';
                    var statusText = $(this).closest('.status-switch').find('.status-text');
                    var eventContainer = $(this).closest('.event-details');

                    $.ajax({
                        url: 'eventos.php',
                        method: 'GET',
                        data: {
                            action: 'update_event_status',
                            id: eventId,
                            status: newStatus
                        },
                        dataType: 'json',
                        success: function(response) {
                            if (response.status === 'success') {
                                statusText.text(newStatus);
                                statusText.removeClass('status-pendiente status-completado');
                                statusText.addClass(newStatus === 'Completado' ? 'status-completado' : 'status-pendiente');

                                if (newStatus === 'Completado') {
                                    eventContainer.addClass('completed-event');
                                } else {
                                    eventContainer.removeClass('completed-event');
                                }

                                refreshCalendar();
                            } else {
                                alert('Error al actualizar el estado');
                                $(this).prop('checked', !$(this).is(':checked'));
                            }
                        },
                        error: function() {
                            alert('Error en la comunicación con el servidor');
                            $(this).prop('checked', !$(this).is(':checked'));
                        }
                    });
                });
            }

            function getEventColor(type) {
                switch (type) {
                    case 'Administrativo':
                        return '#3498db';
                    case 'Admisiones':
                        return '#9b59b6';
                    case 'Academico':
                        return '#e74c3c';
                    default:
                        return '#9b59b6';
                }
            }

            $('#saveEvent').click(function() {
                var formData = {
                    action: 'add_event',
                    nom_eve: $('#nom_eve').val(),
                    fec_ini: $('#fec_ini').val(),
                    fec_fin: $('#fec_fin').val(),
                    tip_eve: $('#tip_eve').val()
                };

                $.ajax({
                    url: 'eventos.php',
                    type: 'POST',
                    data: formData,
                    dataType: 'json',
                    success: function(response) {
                        if (response.status === 'success') {
                            refreshCalendar();
                            $('#addEventModal').modal('hide');
                        } else {
                            alert(response.message || 'Error al guardar el evento');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error("Error:", error);
                        alert('Error en la comunicación con el servidor');
                    }
                });
            });

            $('#deleteEvent').click(function() {
                $('#confirmDeleteModal').modal('show');
            });

            $('#confirmDelete').click(function() {
                if (!currentEventId) {
                    alert('No se ha seleccionado ningún evento para eliminar');
                    return;
                }

                $.ajax({
                    url: 'eventos.php',
                    method: 'GET',
                    data: {
                        action: 'delete_event',
                        id: currentEventId
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.status === 'success') {
                            refreshCalendar();
                            $('#confirmDeleteModal').modal('hide');
                            $('#eventDetailModal').modal('hide');
                            currentEventId = null;
                        } else {
                            alert(response.message || 'Error al eliminar el evento');
                        }
                    },
                    error: function() {
                        alert('Error en la comunicación con el servidor');
                    }
                });
            });
        });
    </script>
</body>

</html>
<?php
$conn->close();
?>
