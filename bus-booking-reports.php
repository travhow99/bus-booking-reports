<?php

/**
 * Plugin Name: Bus Booking Manager Reports Add-on
 * Plugin URI: https://travishowell.net/
 * Description: Plugin to add reports and other functionality to bus booking manager.
 * Version: 1.0
 * Author: Travis Howell
 * Author URI: https://travishowell.com/
 * License: GPLv2 or later
 * Text Domain: th_busreports
 */

if (!defined('ABSPATH')) {
    die;
} // Cannot access pages directly.

function th_drivers_table_create()
{
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    $table_name = $wpdb->prefix . 'th_drivers';
    $sql = "CREATE TABLE $table_name (
        driver_id int(15) NOT NULL AUTO_INCREMENT,
        user_name varchar(55) NOT NULL,
        last_name varchar(55) NOT NULL,
        phone varchar(55),
        email text,
        PRIMARY KEY (driver_id)
    ) $charset_collate;";
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);

    // Create Drivers to routes pivot table
    $table_name = $wpdb->prefix . 'th_drivers_routes';
    $sql = "CREATE TABLE $table_name (
        id int(15) NOT NULL AUTO_INCREMENT,
        driver_id int(9) NOT NULL,
        bus_id int(9) NOT NULL,
        journey_date date NOT NULL,
        PRIMARY KEY (id)
    ) $charset_collate;";
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);

}
register_activation_hook(__FILE__, 'th_drivers_table_create');

include_once(ABSPATH . 'wp-admin/includes/plugin.php');
if (is_plugin_active('woocommerce/woocommerce.php')) {
    require_once(dirname(__FILE__) . "/inc/th_bus_admin_settings.php");
    require_once(dirname(__FILE__) . "/inc/th_bus_enqueue.php");
    require_once(dirname(__FILE__) . "/inc/TH_Driver.php");
    require_once(dirname(__FILE__) . "/inc/TH_Order.php");
    require_once(dirname(__FILE__) . "/inc/TH_Strings.php");
    require_once(dirname(__FILE__) . "/inc/TH_Strings.php");
    require_once(dirname(__FILE__) . "/inc/clean/th_shortcode.php");
}

// Require autoloader
require 'vendor/autoload.php';

use Dompdf\Dompdf;

/**
 * Functions
 */
/**
 * @todo Don't generate empty reports
 */
function th_show_calendar()
{
    $month = isset($_GET['bus_month']) ? $_GET['bus_month'] : date('m');
    $day = isset($_GET['bus_day']) ? $_GET['bus_day'] : date('d');
    $year = isset($_GET['bus_year']) ? $_GET['bus_year'] : date('Y');

    if (isset($_GET['download_list']) && isset($_GET['bus_id']) && isset($_GET['date'])) {
        $dateBuild = $_GET['date'];
        th_show_reports($_GET['bus_id'], $_GET['date']);
        wp_die();
    }

    $dateBuild = "$year-$month-$day";

    th_build_calendar($month, $year, $dateBuild);
    th_add_modal();
    th_add_calendar_scripts();
}

function th_drivers()
{
    $table = TH_Driver::buildTable();

    $html = '';
    $html .= $table;
    $html .= TH_Driver::addButton();

    echo $html;

    th_add_modal();
    th_add_drivers_scripts();
}

function th_build_calendar($month, $year, $dateBuild)
{
    $daysOfWeek = array('SUN', 'MON', 'TUE', 'WED', 'THUR', 'FRI', 'SAT');

    // What is the first day of the month in question?
    $firstDayOfMonth = mktime(0, 0, 0, $month, 1, $year);

    // How many days does this month contain?
    $numberDays = date('t', $firstDayOfMonth);

    // Retrieve some information about the first day of the
    // month in question.
    $dateComponents = getdate($firstDayOfMonth);

    // What is the name of the month in question?
    $monthName = $dateComponents['month'];

    // What is the index value (0-6) of the first day of the
    // month in question.
    $dayOfWeek = $dateComponents['wday'];

    $calendar = '<div>';
    $calendar .= "<table class='th-calendar' border=1 cellspacing=0 cellpadding=0>";
    $calendar .= "<div><h2 style='text-align:center'>$monthName $year</h2></div>";
    $calendar .= "<caption><a class='th-btn month-change' data-direction='previous' style='float:left;'>< Prev Month</a>";

    if ($month !== date('m')) {
        $calendar .= "<a href='/wp-admin/admin.php?page=th_busreports' class='today th-btn th-btn-primary'>Today</a>";
    }

    $calendar .= "<a class='th-btn month-change' data-direction='next' style='float:right;'>Next Month ></a></caption>";
    $calendar .= "<tr>";

    foreach ($daysOfWeek as $day) {
        $calendar .= "<th class='header'>$day</th>";
    }

    $currentDay = 1;

    $calendar .= "</tr><tr>";

    if ($dayOfWeek > 0) {
        $calendar .= "<td colspan='$dayOfWeek'>&nbsp;</td>";
    }

    $month = str_pad($month, 2, "0", STR_PAD_LEFT);

    while ($currentDay <= $numberDays) {
        if ($dayOfWeek == 7) {

            $dayOfWeek = 0;
            $calendar .= "</tr><tr>";
        }

        $currentDayRel = str_pad($currentDay, 2, "0", STR_PAD_LEFT);

        $date = "$year-$month-$currentDayRel";

        $class = ($date === $dateBuild && $month === date('m')) ? 'th-calenader--current-day th-calenader--day' : 'th-calenader--day';
        $calendar .= "<td><div class='th-calenader--date' rel='$date'><div class='$class'>$currentDay</div>";

        $calendar .= th_bus_bookings($date);
        $calendar .= th_bus_bookings($date, TRUE);

        $calendar .= "</div></td>";

        $currentDay++;
        $dayOfWeek++;
    }

    // Complete the row of the last week in month, if necessary
    if ($dayOfWeek != 7) {
        $remainingDays = 7 - $dayOfWeek;
        $calendar .= "<td colspan='$remainingDays'>&nbsp;</td>";
    }

    $calendar .= "</tr>";

    $calendar .= "</table>";
    $calendar .= "</div>";

    echo $calendar;
}

function th_bus_bookings($dateBuild, $fromDia = FALSE, $shortcode = FALSE)
{
    $start = $fromDia ? 'DIA' : 'Ft Collins Harmony Transfer Center';
    $end = $fromDia ? 'Ft Collins Harmony Transfer Center' : 'DIA';

    $classBuild = $start === 'DIA' ? 'th-calendar--northbound' : 'th-calendar--southbound';

    $arr = array(
        'post_type' => array('wbbm_bus'),
        'posts_per_page' => -1,
        'order' => 'ASC',
        'orderby' => 'meta_value',
        'meta_key' => 'wbbm_bus_start_time',
        'meta_query' => array(
            'relation' => 'AND',
            array(
                'key' => 'wbbm_bus_bp_stops',
                'value' => $start,
                'compare' => 'LIKE',
            ),
            array(
                'key' => 'wbbm_bus_next_stops',
                'value' => $end,
                'compare' => 'LIKE',
            ),
        )
    );

    $loop = new WP_Query($arr);

    $output = '';

    while ($loop->have_posts()) {
        $loop->the_post();

        $id = get_the_ID();
        $title = the_title('', '', false);

        $pickups = th_bus_get_pickup_number($id, $dateBuild);

        $values = get_post_custom(get_the_id());
        $total_seat = $values['wbbm_total_seat'][0];
        $available_seat     = th_available_seat($dateBuild);

        $driver = TH_Driver::getRouteDriver($id, $dateBuild);
        $driver_id = $driver->driver_id;

        $driver_initials = $driver ? TH_Driver::initials($driver_id) : '';

        // TODO: Should not count refunded rider
        // TODO: Refunding partials..?
        $sold_seats = $total_seat - $available_seat;

        $class = $sold_seats > 0 ? $classBuild . ' th-calendar--pill-booked' : $classBuild;

        // List Route Time
        $html = "<div class='th-calendar--pill $class' data-bus_id='$id' data-driver_id='$driver_id'>";
        $html .= "<div class='th-calendar--title'>$title</div>";
        $html .= "<div>";
        $html .= "<div class='th-calendar--riders'>$sold_seats</div>";
        $html .= "<div class='th-calendar--driver-initials'>$driver_initials</div>";

        // TODO List Driver
        // List # of riders
        // Generate report button
        $html .= "</div>";
        $html .= "</div>"; // End .th-calendar--pill

        if ($pickups) {
            global $wpdb;
            $table_name = $wpdb->prefix . "wbbm_bus_booking_list";

            $name_build = '<div class="th-attendee-list" data-bus_id="' . $id . '">';

            foreach ($pickups as $p) {
                $query = "SELECT * FROM $table_name WHERE boarding_point='$p->boarding_point' AND journey_date='$dateBuild' AND bus_id='$id' AND (status=2 OR status=1) ORDER BY bus_start ASC";

                $riders = $wpdb->get_results($query);

                if ($riders) {
                    foreach ($riders as $r) {
                        $order = wc_get_order($r->order_id);
                        if ($order->get_status() !== 'completed') continue;


                        $name = "<div style='display:flex;justify-content:space-between;margin-bottom:0.75rem;' data-oid='$r->order_id'>";

                        // @todo GET ACTUal passenge name from DB
                        $name .= '<div><b>Name: </b>'.$r->user_name.'</div>';
                        $name .= '<div><b>Phone: </b>' . $r->user_phone.'</div>';

                        if ($shortcode) {
                                if (!$fromDia) {
                                $name .= '<div>'.$r->boarding_point.'</div>';
                            } else {
                                $name .= '<div>'.$r->droping_point.'</div>';
                            }
                        }
                        $name .= '<div><a class="th-btn" target="_blank" href="/wp-admin/post.php?post=' . $r->order_id . '&action=edit">View Order</a></div>';

                        $name .= '</div>';

                        $name_build .= $name;
                        // $name_build .= $name . ', ' . $o_id->tickets_purchased . ' Ticket(s)</div>';
                    }

                } else {
                    $name_build = '<div>No Seats Booked</div>';
                }

            }
            $name_build .= '</div>';

            $html .= $name_build;
        }


        $output .= $html;
    }

    return $output;
}

function th_bus_get_pickup_number($bus_id, $date)
{
    global $wpdb;
    $table_name = $wpdb->prefix . "wbbm_bus_booking_list";

    $query = "SELECT boarding_point, COUNT(booking_id), bus_start as riders FROM $table_name WHERE bus_id='$bus_id' AND journey_date='$date' AND (status=2 OR status=1) GROUP BY boarding_point, bus_start ORDER BY bus_start ASC";

    $riders_by_location = $wpdb->get_results($query);

    return $riders_by_location;
}

function th_available_seat($date)
{
    $values = get_post_custom(get_the_id());
    $total_seat = $values['wbbm_total_seat'][0];
    $sold_seat = th_bus_get_available_seat(get_the_id(), $date);

    return ($total_seat - $sold_seat) > 0 ? ($total_seat - $sold_seat) : 0;
}

function th_bus_get_available_seat($bus_id, $date)
{
    global $wpdb;
    $table_name = $wpdb->prefix . "wbbm_bus_booking_list";
    $order_ids = $wpdb->get_results("SELECT order_id FROM $table_name WHERE bus_id=$bus_id AND journey_date='$date' AND (status=2 OR status=1)");

    $completed_bookings = [];

    if ($order_ids) {
        foreach ($order_ids as $id) {
            if (th_verify_order_status($id->order_id)) {
                array_push($completed_bookings, $id->order_id);
            }
        }
    }

    return count($completed_bookings);
}

function th_verify_order_status($id)
{
    $order = wc_get_order($id);

    return $order->get_status() === 'completed';
}

function th_generate_report_html($arr, $title)
{
    $filename = $title . " " . date("m-d-Y", strtotime($_GET['date'])) . ".pdf";

    $driver_id = $_GET['driver_id'];

    $driver = TH_Driver::name($driver_id);

    print th_download_report($arr, $filename, $title, $driver);
}

function th_generate_report($id, $dateBuild) {
    global $wpdb;

    $post = get_post($id);

    $title = $post->post_title; // the_title('', '', false);

    $pickups = th_bus_get_pickup_number($id, $dateBuild);

    $results = [
      ['ID', 'Pickup Time', 'Boarding Point', 'Dropping Point', 'First Name', 'Last Name', 'Phone', 'Email'],
    ];

    foreach ($pickups as $p) {
      $table_name = $wpdb->prefix . "wbbm_bus_booking_list";

      $query = "SELECT * FROM $table_name WHERE boarding_point='$p->boarding_point' AND journey_date='$dateBuild' AND bus_id='$id' AND (status=2 OR status=1) ORDER BY bus_start ASC";

      $riders = $wpdb->get_results($query);

      foreach ($riders as $r) {
        $order = wc_get_order($r->order_id);
        if ($order->get_status() !== 'completed') continue;

            $name = explode(' ', $r->user_name);
            $results[] = [
                'ID' => $r->order_id,
                'Pickup Time' => $r->bus_start,
                'Boarding Point' => $r->boarding_point,
                'Dropping Point' => $r->droping_point,
                'First Name' => $name[0],
                'Last Name' => $name[1],
                'Phone' => $r->user_phone,
                'Email' => $order->get_billing_email(),
          ];

      }
    }

    th_generate_report_html($results, $title);
}

function th_show_reports() {
    if (isset($_GET['download_list']) && isset($_GET['bus_id']) && isset($_GET['date'])) {
        ob_clean();
        th_generate_report($_GET['bus_id'], $_GET['date']);
    }
    // wp_die();
}

function th_download_report(array &$array, $filename, $title, $driver="None")
{
    if (count($array) == 0) {
        return null;
    }

    $dompdf = new Dompdf();

    $html = '';

    $style = "<style>
    table, th, td {
      border: 1px solid black;
      border-collapse: collapse;
    }
    .inline {
        display: inline-block;
        height: 80px;
    }
    .shaded {
        background: #e7e7e7;
    }
    </style>
    ";

    $html .= $style;
    // fwrite($fh, $style);
    $logo = th_base64('https://flyawayshuttle.com/wp-content/uploads/2020/07/Logo071720-1.png');

    $header = "<div class='inline' style='width:75%;'><img style='width:33%;' src='$logo'></div>";
    // fwrite($fh, $header);
    $html .= $header;

    $box = "<div class='inline' style='float: right;'>";
    $box .= "<div>" . date("D", strtotime($_GET['date'])) . " $title</div>";
    $box .= "<div>" . date("m-d-Y", strtotime($_GET['date'])) . "</div>";
    /**
     * @todo fill in driver
     */
    $box .= "<div>" . $driver . "</div>";
    $box .= "</div>";
    // fwrite($fh, $box);
    $html .= $box;

    // fwrite($fh, $header);
    //  fputcsv($df, array_keys(reset($array)));

    $table = "<table style='width: 100%;'>";

    foreach ($array as $key => $row) {
        if ($key === 0) {
            $table .= "<thead><tr class='shaded'>";
            foreach ($row as $th) {
                $table .= "<th>$th</th>";
            }
            $table .= "</tr></thead><tbody>";
        } else {
            $table .= "<tr>";
            foreach ($row as $th) {
                $table .= "<th>$th</th>";
            }
            $table .= "</tr>";
        }
   }

    $table .= "</tbody></table>";

    // fwrite($fh, $table);
    $html .= $table;

    $dompdf->loadHtml($html);
    // (Optional) Setup the paper size and orientation
    $dompdf->setPaper('A4', 'portrait');

    // Render the HTML as PDF
    $dompdf->render();

    // Output the generated PDF to Browser
    $dompdf->stream($filename);

    // fclose($fh);

    ob_end_flush();
}

function th_base64($filepath)
{
    $type = pathinfo($filepath, PATHINFO_EXTENSION);
    // Get the image and convert into string
    $img = file_get_contents($filepath);

    // Encode the image string data into base64
    $base64 = 'data:image/' . $type . ';base64,' . base64_encode($img);

    return $base64;
}

function th_download_send_headers($filename)
{
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Content-Description: File Transfer');
    header('Content-type: text/html');
    header("Content-Disposition: attachment; filename={$filename}");
    header('Expires: 0');
    header('Pragma: public');
}


/**
 * @todo Submit button inside form
 */
function th_add_modal()
{
    $html = '
    <div id="th_modal" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="th_modal">
        <div class="modal-underlay"></div>
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"></h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">×</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p>test test </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn th-reports--btn mr-2">
                        <span class="dashicons dashicons-printer"></span>
                    </button>
                    <button type="submit" class="th-btn th-btn-primary">Submit</button>
                    <button type="button" class="th-btn th-btn-secondary" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>';

    echo $html;
}

function th_add_calendar_scripts()
{
    ob_start();
?>
    <script>
        (function($) {
            $('#th_modal button[type="submit"]').hide();

            var urlParams = new URLSearchParams(window.location.search);

            const month_is_set = urlParams.get('bus_month');
            const year_is_set = urlParams.get('bus_year');

            /** Modal functions */
            const Modal = {
                open: function(header = null, html = null) {
                    if (header) {
                        $('.modal-title').html(header);
                    } else {
                        $('.modal-title').html('');
                    }

                    if (html) {
                        $('.modal-body').html(html);
                    } else {
                        $('.modal-body').html('');
                    }

                    $('.modal').addClass('show').show();
                },
                close: function() {
                    $('.modal').removeClass('show').hide();
                },
            }
            $('[data-dismiss="modal"]').click(() => Modal.close());
            $('.modal-underlay').click(function(e) {
                e.preventDefault();

                Modal.close();
            });

            $('body').off('click', '.month-change');
            $('body').on('click', '.month-change', function() {
                const direction = $(this).attr('data-direction');

                let prevDate = month_is_set ? new Date(month_is_set) :  new Date();
                let year = year_is_set ? new Date(year_is_set) :  new Date();
                year = year.getFullYear();

                let newDate = direction === 'next' ? new Date(prevDate.setMonth(prevDate.getMonth() + 1)) : new Date(prevDate.setMonth(prevDate.getMonth() - 1));

                let month = newDate.getMonth();

                month += 1;
                if (month < 10) {
                    month = '0'+month;
                }

                // year
                if (month === 11) {
                    month = '01';
                    year += 1;
                }

                if (month_is_set) {
                    urlParams.set('bus_month', month);

                    if (year && year !== new Date().getFullYear()) urlParams.set('bus_year', year);

                    window.location = `${window.location.origin}${window.location.pathname}?${urlParams.toString()}`;
                } else {
                    window.location = `${window.location.href}&bus_month=${month}`;
                }
            });

            $('body').on('click', '.th-calendar--pill', function() {
                const id = $(this).attr('data-bus_id');
                const driver_id = $(this).attr('data-driver_id');
                const route = $(this).children('.th-calendar--title').text();
                const date = $(this).parents('.th-calenader--date').attr('rel');
                const dropdown = `<?php echo TH_Driver::driverSelect(); ?>`;

                let html = $(this).next(`.th-attendee-list[data-bus_id="${id}"]`).html() || '<span>No Passengers</span>';
                html += '<br>'+dropdown;

                $('#th_modal').attr('data-id', id).attr('data-date', date);
                $('.th-reports--btn').attr('data-id', id).attr('data-date', date);

                Modal.open(route + ', ' + date, html);

                if (driver_id) {
                    $('.th-driver-select').val(driver_id);
                }
            });

            $('body').on('click', '.th-reports--btn', function() {
                if ($('#th_modal .modal-body').html().indexOf('<span>No Passengers</span>') >= 0) return;

                const $rel = $('.th-reports--btn');
                const $id = $rel.attr('data-id');
                const $date = $rel.attr('data-date');
                const $driver_id = $('.th-driver-select').val();

                window.open(`${window.location.href}&bus_id=${$id}&download_list=Y&date=${$date}&driver_id=${$driver_id}`, '_blank');
            });

            $('body').on('change', '.th-driver-select', function() {
                const driver_id = $(this).val();
                const bus_id = $('#th_modal').attr('data-id');
                const journey_date = $('#th_modal').attr('data-date');

                $.post(ajaxurl, {action: 'th_assign_route_driver', driver_id, bus_id, journey_date}, function(response) {
                    // Find Date
                    const container = $(`.th-calenader--date[rel="${journey_date}"]`);
                    const cell = container.find(`.th-calendar--pill[data-bus_id="${bus_id}"]`);
                    // Update data-driver_id
                    cell.attr('data-driver_id', driver_id);
                    // Add initials to cell
                    cell.find('.th-calendar--driver-initials').text(response);

                    // if (response === 'success') location.reload();
                })

            })



            setTimeout(() => {
                console.log('scrolling');
                document.querySelector('.th-calenader--current-day').scrollIntoView();
                window.scrollBy(0, -40);
            }, 250)

        })(jQuery);
    </script>
<?php
    $script = ob_get_clean();
    echo $script;
}

add_action( 'wp_ajax_th_add_driver_action', 'th_add_driver_action' );
function th_add_driver_action() {
    $id = sanitize_text_field($_POST['driver_id']);
    $f_name = sanitize_text_field($_POST['f_name']);
    $l_name = sanitize_text_field($_POST['l_name']);
    $p_number = sanitize_text_field($_POST['p_number']);
    $email = sanitize_text_field($_POST['email']);

    $driver = new TH_Driver($id);

    $driver->first_name = $f_name;
    $driver->last_name = $l_name;
    $driver->phone = $p_number;
    $driver->email = $email;

    if ($id) {
        $driver->update();
    } else {
        $driver->save();
    }

    echo 'success';
    wp_die();
}

add_action( 'wp_ajax_th_assign_route_driver', 'th_assign_route_driver' );
function th_assign_route_driver() {
    global $wpdb;

    $table = $wpdb->prefix . "th_drivers_routes";

    $driver_id = sanitize_text_field($_POST['driver_id']);
    $bus_id = sanitize_text_field($_POST['bus_id']);
    $journey_date = $_POST['journey_date'];

    // Delete first
    $wpdb->delete(
        $table,
        array(
            'bus_id' => $bus_id,
            'journey_date' => $journey_date,
        )
    );

    $wpdb->insert(
        $table,
        array(
          'driver_id' => $driver_id,
          'bus_id' => $bus_id,
          'journey_date' => $journey_date,
        )
    );

    echo TH_Driver::initials($driver_id);
    // echo 'success';
    wp_die();
}

function th_get_driver($bus_id, $journey_date)
{
    return TH_Driver::getRouteDriver($bus_id, $journey_date);
}

function th_add_drivers_scripts()
{
    ob_start();
?>
    <script>
        (function($) {
            $('.th-reports--btn').hide();

            /** Modal functions */
            const Modal = {
                open: function(header = null, html = null) {
                    if (header) {
                        $('.modal-title').html(header);
                    } else {
                        $('.modal-title').html('');
                    }

                    if (html) {
                        $('.modal-body').html(html);
                    } else {
                        $('.modal-body').html('');
                    }

                    $('.modal').addClass('show').show();
                },
                close: function() {
                    $('.modal').removeClass('show').hide();
                },
            }
            $('[data-dismiss="modal"]').click(() => Modal.close());
            $('.modal-underlay').click(function(e) {
                e.preventDefault();

                Modal.close();
            });

            $('body').on('click', '.th-add-driver', function() {
                const html = `<form id="thAddDriverForm">
                    <div class="th-form-group">
                        <label for="firstNameInput">First Name</label>
                        <input type="text" class="form-control" id="firstNameInput" name="firstNameInput" placeholder="First Name" required>
                        <div class="th-form-error">Oops, this is a required field!</div>
                    </div>
                    <div class="th-form-group">
                        <label for="lastNameInput">Last Name</label>
                        <input type="text" class="form-control" id="lastNameInput" name="lastNameInput"  placeholder="Last Name" required>
                        <div class="th-form-error">Oops, this is a required field!</div>
                    </div>
                    <div class="th-form-group">
                        <label for="phoneNumberInput">Phone Number</label>
                        <input type="text" class="form-control" id="phoneNumberInput" name="phoneNumberInput" placeholder="Phone Number">
                    </div>
                    <div class="th-form-group">
                        <label for="emailInput">Email</label>
                        <input type="text" class="form-control" id="emailInput" name="emailInput" placeholder="Email">
                    </div>
                </form>`;

                Modal.open('New Driver', html);
            });

            $('body').on('click', '#th_modal button[type=submit]', function() {
                th_gather_driver_form_info();
            })

            function th_gather_driver_form_info() {
                $('.th-form-group-error').removeClass('th-form-group-error');

                let error = false;

                const driver_id = $('#thAddDriverForm').attr('data-driver_id');

                const f_name = $('#firstNameInput').val();
                const l_name = $('#lastNameInput').val();
                const p_number = $('#phoneNumberInput').val();
                const email = $('#emailInput').val();

                if (!f_name) {
                    $('#firstNameInput').parent().addClass('th-form-group-error');
                    error = true;
                }
                if (!l_name) {
                    $('#lastNameInput').parent().addClass('th-form-group-error');
                    error = true;
                }

                if (error) return;

                $.post(ajaxurl, {action: 'th_add_driver_action', driver_id, f_name, l_name, p_number, email}, function(response) {
                    if (response === 'success') location.reload();
                })
            }

            // Manage driver
            $('body').on('click', '.th-edit-driver', function() {
                const driver_id = $(this).attr('data-driver_id');
                const parent_row = $(this).parents('tr');

                const elements = parent_row.children('td');
                const values = [];

                elements.each((index, el) => values.push($(el).html()))

                const html = `<form id="thAddDriverForm" data-driver_id="${driver_id}">
                    <div class="th-form-group">
                        <label for="firstNameInput">First Name</label>
                        <input type="text" class="form-control" id="firstNameInput" name="firstNameInput" placeholder="First Name" value="${values[0]}" required>
                        <div class="th-form-error">Oops, this is a required field!</div>
                    </div>
                    <div class="th-form-group">
                        <label for="lastNameInput">Last Name</label>
                        <input type="text" class="form-control" id="lastNameInput" name="lastNameInput"  placeholder="Last Name" value="${values[1]}" required>
                        <div class="th-form-error">Oops, this is a required field!</div>
                    </div>
                    <div class="th-form-group">
                        <label for="phoneNumberInput">Phone Number</label>
                        <input type="text" class="form-control" id="phoneNumberInput" name="phoneNumberInput" placeholder="Phone Number" value="${values[2]}">
                    </div>
                    <div class="th-form-group">
                        <label for="emailInput">Email</label>
                        <input type="text" class="form-control" id="emailInput" name="emailInput" placeholder="Email" value="${values[3]}">
                    </div>
                </form>`;

                Modal.open('Edit Driver', html);
            })


        })(jQuery);
    </script>
<?php
    $script = ob_get_clean();
    echo $script;
}

/** Order editing functions */
// function th_get_order($bus_id, $journey_date)
// {
//     return TH_Driver::getRouteDriver($bus_id, $journey_date);
// }

function th_orders()
{
    $table = TH_Order::buildTable();

    $html = '<h2>In progress, do not use!</h2>';
    $html .= '<div><button class="th-add-order">Add Order</button></div>';
    $html .= $table;

    echo $html;

    th_add_modal();
    th_add_orders_scripts();
}

add_action( 'wp_ajax_th_add_order_action', 'th_add_order_action' );
function th_add_order_action() {
    $id = sanitize_text_field($_POST['booking_id']);
    $user_name = sanitize_text_field($_POST['user_name']);
    $user_phone = sanitize_text_field($_POST['user_phone']);

    $bus_id = sanitize_text_field($_POST['bus_id']);
    $bus_start = sanitize_text_field($_POST['bus_start']);
    $journey_date = sanitize_text_field($_POST['journey_date']);
    $boarding_point = sanitize_text_field($_POST['boarding_point']);
    $droping_point = sanitize_text_field($_POST['droping_point']);
    @$ticket_count = sanitize_text_field($_POST['ticket_count']);
    @$order_id = sanitize_text_field($_POST['order_id']);

    if ($id) {
        $order = new TH_Order($id);
    
        $order->user_name = $user_name;
        $order->user_phone = $user_phone;
    
        $order->bus_id = $bus_id;
        $order->bus_start = $bus_start;
        $order->journey_date = $journey_date;
        $order->boarding_point = $boarding_point;
        $order->droping_point = $droping_point;
    
        if ($id) {
            $order->update();
        } else {
            wp_die();
        }
    
        echo 'success';
    } else {
        // If creating a new order
        $count = 1;
        while ($count <= $ticket_count) {
            $order = new TH_Order();

            $order->order_id = $order_id;
            $order->user_name = $user_name;
            $order->user_phone = $user_phone;
        
            $order->bus_id = $bus_id;
            $order->bus_start = $bus_start;
            $order->journey_date = $journey_date;
            $order->boarding_point = $boarding_point;
            $order->droping_point = $droping_point;
    
            $order->save();

            $count++;
        }

        echo 'success';
    }

    wp_die();
}


function th_add_orders_scripts()
{
    ob_start();
    ?>
        <script>
            (function($) {
                $('.th-reports--btn').hide();

                /** Modal functions */
                const Modal = {
                    open: function(header = null, html = null) {
                        if (header) {
                            $('.modal-title').html(header);
                        } else {
                            $('.modal-title').html('');
                        }

                        if (html) {
                            $('.modal-body').html(html);
                        } else {
                            $('.modal-body').html('');
                        }

                        $('.modal').addClass('show').show();
                    },
                    close: function() {
                        $('.modal').removeClass('show').hide();
                    },
                }
                $('[data-dismiss="modal"]').click(() => Modal.close());
                $('.modal-underlay').click(function(e) {
                    e.preventDefault();

                    Modal.close();
                });

                $('body').on('click', '#th_modal button[type=submit]', function() {
                    th_gather_order_form_info();
                })

                function th_to_24hr_time(str) {

                }

                function th_gather_order_form_info() {
                    $('.th-form-group-error').removeClass('th-form-group-error');

                    let error = false;

                    const booking_id = $('#thAddOrderForm').attr('data-booking_id');

                    const user_name = $('#userNameInput').val();
                    const user_phone = $('#userPhoneInput').val();
                    const bus_id = $('#busStartInput').val();
                    const bus_start = $('#busStartInput option:selected').text();
                    const journey_date = $('#journeyDateInput').val();
                    const boarding_point = $('#boardingPointInput').val();
                    const droping_point = $('#dropingPointInput').val();

                    const data = {action: 'th_add_order_action', booking_id, user_name, user_phone, bus_id, bus_start, journey_date, boarding_point, droping_point};

                    if (!user_name) {
                        $('#userNameInput').parent().addClass('th-form-group-error');
                        error = true;
                    }
                    if (!user_phone) {
                        $('#userPhoneInput').parent().addClass('th-form-group-error');
                        error = true;
                    }
                    if (!bus_id || !bus_start) {
                        $('#busStartInput').parent().addClass('th-form-group-error');
                        error = true;
                    }
                    if (!journey_date) {
                        $('#journeyDateInput').parent().addClass('th-form-group-error');
                        error = true;
                    }
                    if (!boarding_point) {
                        $('#boardingPointInput').parent().addClass('th-form-group-error');
                        error = true;
                    }
                    if (!droping_point) {
                        $('#dropingPointInput').parent().addClass('th-form-group-error');
                        error = true;
                    }

                    // Adding new
                    if ($('#ticketInput').length) {
                        let ticket_count = $('#ticketInput').val();

                        if (!ticket_count) {
                            $('#ticketInput').parent().addClass('th-form-group-error');
                            error = true;
                        } else {
                            data.ticket_count = ticket_count;
                        }

                        const order_id = $('#orderIdInput').val();
                        if (!order_id) {
                            $('#orderIdInput').parent().addClass('th-form-group-error');
                            error = true;
                        } else {
                            data.order_id = order_id;
                        }
                    }

                    if (error) return;

                    $.post(ajaxurl, data, function(response) {
                        if (response === 'success') location.reload();
                    })
                }

                // Manage driver
                $('body').on('click', '.th-add-order', function() {
                    // const booking_id = $(this).attr('data-booking_id');
                    // const parent_row = $(this).parents('tr');

                    // const elements = parent_row.children('td');
                    // const values = [];

                    // let bus_id;

                    // elements.each((index, el) => {
                    //     values.push($(el).html())

                    //     if ($(el).attr('data-bus_id')) {
                    //         bus_id = $(el).attr('data-bus_id');
                    //     }
                    // })

                    // const busTime = values[2]; // th_to_24hr_time(values[3]);

                    const routeSelect = $('#thRoutesDropdown').html();
                    const timeSelect = $('#thTimesDropdown').html();
                    const orderSelect = $('#thOrdersDropdown').html();

                    console.log(routeSelect)
                    console.log(timeSelect)

                    const html = `<form id="thAddOrderForm">
                        <div class="th-form-group">
                            <label for="userNameInput">Name</label>
                            <input type="text" class="form-control" id="userNameInput" name="userNameInput" placeholder="Name" required>
                        </div>
                        <div class="th-form-group">
                            <label for="userPhoneInput">Phone Number</label>
                            <input type="tel" class="form-control" id="userPhoneInput" name="userPhoneInput" placeholder="Phone Number">
                        </div>

                        <div class="th-form-group">
                            <label for="busStartInput">Time</label>
                            <select id="busStartInput" name="busStartInput">
                                ${timeSelect}
                            </select>
                        </div>
                        <div class="th-form-group">
                            <label for="journeyDateInput">Bus Date</label>
                            <input type="date" class="form-control" id="journeyDateInput" name="journeyDateInput" placeholder="Bus Date" required>
                            <div class="th-form-error">Oops, this is a required field!</div>
                        </div>

                        <div class="th-form-group" id="thFormBPoint">
                            <label for="boardingPointInput">Boarding Point</label>
                            <select id="boardingPointInput" name="boardingPointInput">
                                ${routeSelect}
                            </select>
                        </div>
                        <div class="th-form-group" id="thFormDPoint">
                            <label for="dropingPointInput">Dropping Point</label>
                            <select id="dropingPointInput" name="dropingPointInput">
                                ${routeSelect}
                            </select>
                        </div>
                        <div class="th-form-group" id="thFormTickets">
                            <label for="ticketInput">Tickets</label>
                            <input class="form-control" type="number" min="1" id="ticketInput" name="ticketInput" />
                            <div class="th-form-error">Oops, this is a required field!</div>
                        </div>
                        <div class="th-form-group" id="thFormOrder">
                            <label for="orderIdInput">Order #</label>
                            <select id="orderIdInput" name="orderIdInput">
                                ${orderSelect}
                            </select>
                        </div>

                    </form>`;

                    Modal.open('Create Order', html);

                    // let time = values[2];
                    // time = time[0] == 0 ? time.substr(1) : time;

                    setTimeout(() => {
                        // Set dropdown values
                        // $('#busStartInput').val(bus_id);
                        // $('#boardingPointInput').val(values[4]);
                        // $('#dropingPointInput').val(values[5]);

                        th_updateDisabledOptions();
                    }, 10);
                });

                // Manage order
                $('body').on('click', '.th-edit-order', function() {
                    const booking_id = $(this).attr('data-booking_id');
                    const parent_row = $(this).parents('tr');

                    const elements = parent_row.children('td');
                    const values = [];

                    let bus_id;

                    elements.each((index, el) => {
                        values.push($(el).html())

                        if ($(el).attr('data-bus_id')) {
                            bus_id = $(el).attr('data-bus_id');
                        }
                    })

                    const busTime = values[2]; // th_to_24hr_time(values[3]);

                    const routeSelect = $('#thRoutesDropdown').html();
                    const timeSelect = $('#thTimesDropdown').html();

                    console.log(routeSelect)
                    console.log(timeSelect)

                    const html = `<form id="thAddOrderForm" data-booking_id="${booking_id}">
                        <div class="th-form-group">
                            <label for="userNameInput">Name</label>
                            <input type="text" class="form-control" id="userNameInput" name="userNameInput" placeholder="Name" value="${values[0]}" required>
                        </div>
                        <div class="th-form-group">
                            <label for="userPhoneInput">Phone Number</label>
                            <input type="tel" class="form-control" id="userPhoneInput" name="userPhoneInput" placeholder="Phone Number" value="${values[1]}">
                        </div>

                        <div class="th-form-group">
                            <label for="busStartInput">Time</label>
                            <select id="busStartInput" name="busStartInput">
                                ${timeSelect}
                            </select>
                        </div>
                        <div class="th-form-group">
                            <label for="journeyDateInput">Bus Date</label>
                            <input type="date" class="form-control" id="journeyDateInput" name="journeyDateInput" placeholder="Bus Date" value="${values[3]}" required>
                            <div class="th-form-error">Oops, this is a required field!</div>
                        </div>

                        <div class="th-form-group" id="thFormBPoint">
                            <label for="boardingPointInput">Boarding Point</label>
                            <select id="boardingPointInput" name="boardingPointInput">
                                ${routeSelect}
                            </select>
                        </div>
                        <div class="th-form-group" id="thFormDPoint">
                            <label for="dropingPointInput">Dropping Point</label>
                            <select id="dropingPointInput" name="dropingPointInput">
                                ${routeSelect}
                            </select>
                        </div>
                    </form>`;

                    Modal.open('Edit Order', html);

                    let time = values[2];
                    time = time[0] == 0 ? time.substr(1) : time;

                    setTimeout(() => {
                        // Set dropdown values
                        $('#busStartInput').val(bus_id);
                        $('#boardingPointInput').val(values[4]);
                        $('#dropingPointInput').val(values[5]);

                        th_updateDisabledOptions();
                    }, 10);
                });

                const dia = ['DIA'];
                const noco = ['Centerra Park and Ride', 'Ft Collins Harmony Transfer Center', 'Windsor Park and Ride'];

                const southbound_routes = ['179', '153', '49', '183', '185'];
                const northbound_routes = ['181', '187', '189', '191', '193'];

                $('body').on('change', '#busStartInput', function() {
                    th_updateDisabledOptions();
                });

                
                function th_updateDisabledOptions() {
                    const bus_id = $('#busStartInput').val();

                    if (southbound_routes.indexOf(bus_id) >= 0) {
                        if ($('#boardingPointInput').val() === 'DIA') $('#boardingPointInput').val('Ft Collins Harmony Transfer Center');
                        if ($('#dropingPointInput').val() !== 'DIA') $('#dropingPointInput').val('DIA');

                        $('#boardingPointInput option[data-location="dia"]').attr('disabled', true);
                        $('#boardingPointInput option[data-location="noco"]').attr('disabled', false);

                        $('#dropingPointInput option[data-location="dia"]').attr('disabled', false);
                        $('#dropingPointInput option[data-location="noco"]').attr('disabled', true);
                    } else if (northbound_routes.indexOf(bus_id) >= 0) {
                        if ($('#boardingPointInput').val() !== 'DIA') $('#boardingPointInput').val('DIA');
                        if ($('#dropingPointInput').val() === 'DIA') $('#dropingPointInput').val('Ft Collins Harmony Transfer Center');

                        $('#boardingPointInput option[data-location="dia"]').attr('disabled', false);
                        $('#boardingPointInput option[data-location="noco"]').attr('disabled', true);

                        $('#dropingPointInput option[data-location="dia"]').attr('disabled', true);
                        $('#dropingPointInput option[data-location="noco"]').attr('disabled', false);
                    }
                }

            })(jQuery);
        </script>
    <?php
    $script = ob_get_clean();
    echo $script;
}

function th_add_manifest_scripts()
{
    ob_start();
?>
    <script>
        (function($) {
            $('body').on('change', '#date', function() {
                var urlParams = new URLSearchParams(window.location.search);

                const day_is_set = urlParams.get('date');

                urlParams.set('date', $(this).val());
                if (day_is_set) {
                    window.location = `${window.location.origin}${window.location.pathname}?${urlParams.toString()}`;
                } else {
                    window.location = `${window.location.href}?${urlParams.toString()}`;
                }
            })

            $('body').on('click', '#today', function() {
                window.location = `${window.location.origin}${window.location.pathname}`;
            });
        })(jQuery);
        </script>
    <?php
    $script = ob_get_clean();

    return $script;
}
?>
