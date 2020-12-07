<?php

if ( !class_exists('TH_Order' ) ) {
    class TH_Order
    {
        // public $thdb = $wpdb;
        public static $attributes = ['booking_id', 'order_id', 'bus_id', 'user_name', 'user_phone', 'user_email', 'bus_start', 'journey_date', 'boarding_point', 'droping_point'];

        public static $table = "wbbm_bus_booking_list";

        function __construct($id=null)
        {
            global $wpdb;

            $this->wpdb = $wpdb;
            $this->table = $this->wpdb->prefix . static::$table;

            if ($id) {
                $this->id = $id;

                $order = $this->wpdb->get_results("SELECT * FROM $this->table WHERE `booking_id`='$id'");

                if (is_array($order[0]) || is_object($order[0])) {
                    foreach ($order[0] as $key => $val) {
                        $this->{$key} = $val;
                    }
                }

            } else {
                foreach (self::$attributes as $att) {
                    $this->{$att} = '';
                }
            }

            return $this;
        }

        public function update()
        {
            $this->wpdb->update(
                $this->table,
                array(
                    'boarding_point' => $this->boarding_point,
                    'droping_point' => $this->droping_point,
                    'user_name' => $this->user_name,
                    'user_email' => $this->user_email,
                    'user_phone' => $this->user_phone,
                    'bus_start' => $this->bus_start,
                    'journey_date' => $this->journey_date,
                ),
                array(
                    'booking_id' => $this->id,
                )
            );

            return true;
        }

        public static function all()
        {
            global $wpdb;

            $table = $wpdb->prefix . static::$table;

            $orders = $wpdb->get_results("SELECT * FROM $table WHERE booking_id > 113 AND status = 2 ORDER BY booking_id DESC");

            return $orders;
        }

        public static function buildTable()
        {
            $orders = self::all();

            if (!count($orders)) {
                return '<h3>No orders exist!</h3>';
            }

            $table = "<table class='th-table'><thead><tr>";

            foreach (self::$attributes as $a) {
                if ($a === 'booking_id' || $a === 'order_id' || $a === 'bus_id') continue;

                $table .= "<th>". TH_Strings::snake_to_proper_case($a) . "</th>";
            }

            $table .= "<th></th>"; // Manage button

            $table .= "</tr></thead>";

            $table .= "<tbody>";

            foreach ($orders as $d) {
                $id = $d->booking_id;
                $order_id = $d->order_id;
                $bus_id = $d->bus_id;
                $boarding_point = $d->boarding_point;
                $droping_point = $d->droping_point;
                $user_name = $d->user_name;
                $user_email = $d->user_email;
                $user_phone = $d->user_phone;
                $bus_start = $d->bus_start;
                $journey_date = $d->journey_date;


                $table .= "<tr data-booking_id='$id'><td>$user_name</td><td>$user_phone</td><td>$user_email</td><td>$bus_start</td><td>$journey_date</td><td>$boarding_point</td><td>$droping_point</td><td><button class='th-btn th-edit-order' data-order_id='$id'><span class='dashicons dashicons-admin-generic'></span></button></td></tr>";
            }

            // Add to table extra dropdown resources
            self::gatherDropdownData();

            return $table;
        }

        public static function gatherDropdownData()
        {
            $routes = get_terms(array(
                'taxonomy' => 'wbbm_bus_stops',
                'hide_empty' => false,
            ));

            $html = '<select id="thRoutesDropdown" style="display: none;">';

            foreach ($routes as $r) {
                $name = $r->name;
                $html .= "<option value='$name'>$name</option>";
            }

            $html .= "</select>";

            // Get bus times
            $arr = array(
              'post_type' => array('wbbm_bus'),
              'posts_per_page' => -1,
              'order' => 'ASC',
              'orderby' => 'meta_value',
              'meta_key' => 'wbbm_bus_start_time',
            );
          
            $loop = new WP_Query($arr);
            $posts = $loop->posts;

            $html .= "<select id='thTimesDropdown' style='display: none;'>";

            foreach ($posts as $p) {
                $title = explode(' ', $p->post_title);

                $val = $title[0] . ' ' . $title[1]; 

                $html .= "<option value='$val'>$val</option>";
            }

            $html .= "</select>";
              
            echo $html;
        }
    }
}
