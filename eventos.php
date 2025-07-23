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
function getEventColor($type) {
    switch($type) {
        case 'Administrativo': return '#3498db';
        case 'Admisiones': return '#2ecc71';
        case 'Academico': return '#e74c3c';
        default: return '#9b59b6';
    }
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
            
            while($row = $result->fetch_assoc()) {
                $events[] = array(
                    'id' => $row['id_eve'],
                    'title' => $row['nom_eve'],
                    'start' => $row['fec_ini'],
                    'end' => $row['fec_fin'],
                    'color' => getEventColor($row['tip_eve']),
                    'tip_eve' => $row['tip_eve'],
                    'est_eve' => $row['est_eve'],
                    'ejecutivo' => $row['nombre_ejecutivo'] ?: $row['nom_eje'],
                    'plantel' => $row['nombre_plantel'] ?: $row['nom_pla']
                );
            }
            
            echo json_encode($events);
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
            
            while($row = $result->fetch_assoc()) {
                $events[] = array(
                    'id' => $row['id_eve'],
                    'title' => $row['nom_eve'],
                    'start' => $row['fec_ini'],
                    'end' => $row['fec_fin'],
                    'type' => $row['tip_eve'],
                    'status' => $row['est_eve'],
                    'ejecutivo' => $row['nombre_ejecutivo'] ?: $row['nom_eje'],
                    'plantel' => $row['nombre_plantel'] ?: $row['nom_pla']
                );
            }
            
            echo json_encode($events);
            break;
            
        case 'update_event_status':
            $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
            $status = isset($_GET['status']) ? $conn->real_escape_string($_GET['status']) : '';
            
            if ($id <= 0 || !in_array($status, ['Pendiente', 'Completado'])) {
                echo json_encode(array('status' => 'error', 'message' => 'Parámetros inválidos'));
                break;
            }
            
            $sql = "UPDATE eventos SET est_eve = '$status' WHERE id_eve = $id";
            
            if ($conn->query($sql)) {
                echo json_encode(array('status' => 'success'));
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
                echo json_encode(array('status' => 'success'));
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
        echo json_encode(array('status' => 'success'));
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
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        /* Cabecera del calendario */
        .fc-toolbar {
            padding: 15px;
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            margin-bottom: 20px !important;
        }
        
        .fc-toolbar h2 {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--dark-color);
            margin: 0;
        }
        
        /* Botones de navegación mejorados */
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
        
        /* Iconos de flechas mejorados */
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
        
        .fc-prev-button, .fc-next-button {
            min-width: 40px !important;
            padding: 8px 12px !important;
        }
        
        .fc-button:active, .fc-button:focus {
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
        
        /* Eventos */
        .fc-event {
            border: none !important;
            border-radius: 6px !important;
            padding: 5px 8px !important;
            font-size: 0.9rem;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            transition: transform 0.2s ease;
            color: white !important;
            text-shadow: 0 1px 1px rgba(0,0,0,0.3);
        }
        
        .fc-event:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
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
        
        /* Eventos completados */
        .completed-event {
            opacity: 0.7;
            text-decoration: line-through;
        }
        
        /* Modales */
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
        
        .form-control, .form-select {
            border-radius: 8px;
            padding: 10px 15px;
            border: 1px solid #e0e0e0;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(67, 97, 238, 0.2);
        }
        
        /* Switch */
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
        
        input:checked + .slider {
            background-color: var(--success-color);
        }
        
        input:checked + .slider:before {
            transform: translateX(26px);
        }
        
        /* Iconos en botones */
        .btn i {
            margin-right: 8px;
        }
        .event-type {
            color: #fff;
            padding: 10px;
        }
        .btn-delete{
            color: #fff;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1 class="text-center mb-4" style="color: var(--primary-color); font-weight: 700;">Calendario de Eventos</h1>
        <div id="calendar-container">
            <div id="calendar"></div>
        </div>
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

    <!-- jQuery (requerido por Bootstrap) -->
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

        // Inicializar calendario
        var calendar = $('#calendar').fullCalendar({
            timeZone: 'UTC',
            defaultView: 'month',
            header: {
                left: 'prev,next today',
                center: 'title',
                right: 'month'
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
            events: 'eventos.php?action=get_events',
            dayClick: function(date, jsEvent, view) {
                if (!$(jsEvent.target).hasClass('fc-event')) {
                    $('#eventForm')[0].reset();
                    $('#fec_ini').val(date.format('YYYY-MM-DD'));
                    $('#fec_fin').val(date.format('YYYY-MM-DD'));
                    $('#addEventModal').modal('show');
                }
            },
            eventClick: function(calEvent, jsEvent, view) {
                currentEventId = calEvent.id;
                showEventDetails(calEvent);
                $('#eventDetailModal').modal('show');
            },
            eventRender: function(event, element) {
                element.attr('title', 
                    event.title + '\n' +
                    'Área: ' + event.tip_eve + '\n' +
                    'Inicio: ' + moment(event.start).format('DD/MM/YYYY') + '\n' +
                    'Fin: ' + (event.end ? moment(event.end).format('DD/MM/YYYY') : 'N/A') + '\n' +
                    'Estado: ' + (event.est_eve || 'Pendiente') + '\n' +
                    'Ejecutivo: ' + (event.ejecutivo || 'Raul Callejas') + '\n' +
                    'Plantel: ' + (event.plantel || 'Ecatepec')
                );
                element.tooltip({
                    placement: 'top',
                    container: 'body'
                });
                
                if (event.est_eve === 'Completado') {
                    element.addClass('completed-event');
                }
            }
        });

        function showEventDetails(event) {
            var modalContent = $('#eventDetailContent');
            currentEventId = event.id;
            
            var color = getEventColor(event.tip_eve);
            var startDate = moment(event.start);
            var endDate = moment(event.end);
            
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
                            
                            $('#calendar').fullCalendar('refetchEvents');
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
            switch(type) {
                case 'Administrativo': return '#3498db';
                case 'Admisiones': return '#2ecc71';
                case 'Academico': return '#e74c3c';
                default: return '#9b59b6';
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
                        $('#calendar').fullCalendar('refetchEvents');
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
                        $('#calendar').fullCalendar('refetchEvents');
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