<?php
include 'db_connect.php';

// Check if it's a GET request and if the required parameters are set
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id']) && isset($_GET['section_id'])) {
    $course_offering_info_id = $_GET['id'];
    $section_id = $_GET['section_id'];

    // Query to get course_offering_info information
    $course_offering_info_result = $conn->query("SELECT * FROM course_offering_info WHERE id = $course_offering_info_id");
    if (!$course_offering_info_result) {
        throw new Exception("Error fetching course offering information: " . $conn->error);
    }
    $course_offering_info = $course_offering_info_result->fetch_assoc();

    // Query to get inactive room schedules
    $inactive_result = $conn->query("SELECT * FROM schedules WHERE is_active = 0");
    if (!$inactive_result) {
        throw new Exception("Error fetching inactive room schedules: " . $conn->error);
    }
    $inactive = [];
    while ($row = $inactive_result->fetch_assoc()) {
        $inactive[] = $row;
    }

    $is_comlab = $conn->query("SELECT is_comlab FROM courses WHERE id = " . $course_offering_info['courses_id'])->fetch_assoc()['is_comlab'];
}
?>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@fullcalendar/core@6.1.11/index.global.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@fullcalendar/daygrid@6.1.11/index.global.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@fullcalendar/timegrid@6.1.11/index.global.min.js"></script>

<div class="container-fluid">
    <section class="content-header col-md-12 d-flex align-items-center justify-content-between mb-3">
        <h3><i class="fa fa-calendar-check"></i> Course Scheduling</h3>
        <ol class="breadcrumb bg-transparent p-0 m-0">
            <li class="breadcrumb-item"><a href="index.php?page=home"><i class="fa fa-home"></i> Home</a></li>
            <li class="breadcrumb-item active"> Course Management</li>
            <li class="breadcrumb-item active">Course Scheduling</li>
        </ol>
    </section>
    <section class="content">
        <div class="row">
            <div class="col-sm-4">
                <div class="card card-solid card-default shadow mb-4">
                    <div class="card-header bg-secondary text-white">
                        <h5 class="card-title">Inactive Schedules</h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($inactive)) : ?>
                            <div class="table-responsive">
                                <table class="table table-bordered">
                                    <thead>
                                        <tr>
                                            <th>Schedule</th>
                                            <th>Attach</th>
                                            <th>Delete</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($inactive as $schedule) : ?>
                                            <tr>
                                                <td><?php echo $schedule['day']; ?> <?php echo date('g:iA', strtotime($schedule['time_starts'])); ?>-<?php echo date('g:iA', strtotime($schedule['time_end'])); ?></td>

                                                <td><a href="SchedAjax/CS_get_room_available.php?id=<?= $schedule['id']; ?>&course_offering_info_id=<?= $course_offering_info_id; ?>" class="btn btn-flat btn-block btn-success"><i class="fa fa-plus-circle"></i></a></td>

                                                <td><a href="SchedAjax/delete_schedule.php?id=<?= $schedule['id']; ?>&course_offering_info_id=<?= $course_offering_info_id; ?>" onclick="return confirm('Do you wish to continue?')" class="btn btn-flat btn-block btn-danger"><i class="fa fa-times"></i></a></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else : ?>
                            <div class="alert alert-danger" role="alert">
                                <h5><strong>No Course Offered Found!</strong></h5>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-sm-8">
                <div class="card card-default shadow mb-4">
                    <div class="card-header bg-transparent">
                        <h5 class="card-title">Schedule</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-sm-3">
                                <div class="form-group">
                                    <label>Day</label>
                                    <select class="form-control" id="day">
                                        <option>Day</option>
                                        <option value="M">Monday</option>
                                        <option value="T">Tuesday</option>
                                        <option value="W">Wednesday</option>
                                        <option value="Th">Thursday</option>
                                        <option value="F">Friday</option>
                                        <option value="Sa">Saturday</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-sm-4">
                                <div class="form-group">
                                    <label>Time Start</label>
                                    <div class="input-group">
                                        <input type="time" class="form-control timepicker" id="time_start" min="07:00" max="20:30" step="1800">
                                        <div class="invalid-feedback"></div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-sm-4">
                                <div class="form-group">
                                    <label>Time End</label>
                                    <div class="input-group">
                                        <input type="time" class="form-control timepicker" id="time_end" min="07:00" max="20:30" step="1800">
                                        <div class="invalid-feedback"></div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-sm-1">
                                <label>Add</label>
                                <a onclick="addSchedule(day.value, time_start.value, time_end)" class="btn btn-flat btn-success text-white"><i class="fa fa-plus-circle"></i></a>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer no-padding bg-transparent mb-3 p-2">
                        <div class="col-sm-12">
                            <div id="calendar"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<div id="myModal" class="modal fade" role="dialog">
    <div id='displayroom'></div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        var calendarEl = document.getElementById('calendar');
        var calendar = new FullCalendar.Calendar(calendarEl, {
            height: "auto",
            firstDay: 1,
            dayHeaderFormat: {
                weekday: 'short'
            },
            initialView: 'timeGridWeek',
            hiddenDays: [0],
            slotMinTime: '07:00:00',
            slotMaxTime: '22:00:00',
            allDaySlot: false,
            headerToolbar: false,
            eventOverlap: false,
            eventClick: function(info) {
                var boolean = confirm('Clicking the OK button button will change the status of the schedule. Do you wish to continue?');
                if (boolean == true) {
                    window.open('/admin/course_scheduling/remove_schedule/' + info.event.id + '/' + info.event.extendedProps.offering_id, '_self');
                }
            },
            eventDidMount: function(info) {
                info.el.querySelector('.fc-title').innerHTML = info.el.querySelector('.fc-title').textContent;
            },
            events: function(fetchInfo, successCallback, failureCallback) {
                fetch('ajax.php?action=get_schedule&course_offering_info_id=<?php echo $course_offering_info_id; ?>')
                    .then(function(response) {
                        return response.json();
                    })
                    .then(function(data) {
                        successCallback(data);
                    })
                    .catch(function(error) {
                        console.error('There was an error while fetching schedule data:', error);
                        failureCallback(error);
                    });
            }
        });

        calendar.render();
    });


    function addSchedule(day, time_start, time_end) {
        var isValid = true;
        var startTime = $('#time_start').val();
        var endTime = $('#time_end').val();

        if (!day || !time_start || !time_end) {
            isValid = false;
            alert_toast('Please fill in all fields.', 'danger');
        } else if (startTime >= endTime) {
            isValid = false;
            $('#time_end').addClass('is-invalid');
            $('#time_end').siblings('.invalid-feedback').text('End time must be after start time.');
        } else {
            $('#time_end').removeClass('is-invalid');
            $('#time_end').siblings('.invalid-feedback').text('');
        }

        if (isValid) {
            $.ajax({
                type: "GET",
                url: "SchedAjax/CS_get_room_available.php",
                data: {
                    day: day,
                    time_start: time_start,
                    time_end: time_end,
                    course_offering_info_id: courseOfferingInfoId,
                    section_id: sectionId
                },
                success: function(data) {
                    $('#displayroom').html(data).fadeIn();
                    $('#myModal').modal('show');
                }
            });
        }
    }

    $('#time_start').on('change', function() {
        <?php if (isset($is_comlab) && $is_comlab == 1) : ?>
            $('#time_end').val(moment(this.value, "HH:mm").add(3, 'hours').format("HH:mm"));
        <?php endif; ?>

        var startTime = this.value;
        var endTime = $('#time_end').val();

        if (startTime >= endTime) {
            $('#time_end').addClass('is-invalid');
            $('#time_end').siblings('.invalid-feedback').text('End time must be greater than start time.');
        } else {
            $('#time_end').removeClass('is-invalid');
            $('#time_end').siblings('.invalid-feedback').text('');
        }
    });
</script>

<style>
    .card-footer {
        border-top: none;
    }
</style>