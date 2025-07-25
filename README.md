# 📅 Sistema de Gestión de Eventos - Calendario Interactivo con FullCalendarJS

<p align="center">
  <img src="https://github.com/ScorpionCallejas/p4_ahjende/blob/main/FullCalendarJs.png" alt="Vista previa del calendario" width="70%">
</p>

## 🔍 Descripción general
Sistema completo de gestión de eventos con calendario interactivo desarrollado con:
- **Backend**: PHP + MySQL
- **Frontend**: FullCalendarJS + jQuery + Bootstrap 5
- **Comunicación**: WebSocket + AJAX

Principales funcionalidades:
- ➕ Creación dinámica de eventos
- ✅ Seguimiento de estados (Pendiente/Completado)
- 🗑 Eliminación segura de eventos
- 🔄 Actualización en tiempo real
- 📊 Vistas de calendario y lista
- 📤 Exportación a PDF

## 🚀 Características principales

### Backend (PHP)
| Función | Descripción | Implementación |
|---------|-------------|----------------|
| Conexión DB | Conexión segura MySQLi | Uso de prepared statements |
| CRUD | Operaciones completas | PHP + MySQL |
| API REST | Endpoints AJAX | JSON responses |
| Sistema de colores | Asignación automática | Función getEventColor() |
| Validación | Seguridad en servidor | Filtrado de inputs |
| WebSocket | Notificaciones tiempo real | BroadcastUpdate() |

### Frontend (JavaScript)
| Función | Tecnología | Detalle |
|---------|------------|---------|
| Interfaz | FullCalendar v3 | Renderizado calendario |
| Diseño | Bootstrap 5 | Responsive design |
| Tooltips | CSS Custom | Información detallada |
| Estados | jQuery | Toggle Pendiente/Completado |
| Exportación | jsPDF + html2canvas | Generación de PDFs |

## 🛠 Stack Tecnológico

```mermaid
pie
    title Arquitectura del Sistema
    "Frontend (JS/jQuery)" : 35
    "FullCalendar" : 20
    "Backend (PHP)" : 25
    "Base de Datos" : 10
    "WebSocket" : 10
