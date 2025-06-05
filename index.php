<?php
/**
 * Tyre Management
 *
 * @package           Tyres Management
 * @author            Uzziel Lite
 * @copyright         2025 emmerce inc.
 * @license           GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       Tyre Management
 * Plugin URI:        https://github.com/uzziellite/silverstone-tyres
 * Description:       Fetch the data for the tyres of different car models based on their modifications and years
 * Version:           1.1.0
 * Requires at least: 5.2
 * Requires PHP:      8.1
 * Author:            Uzziel Lite
 * Author URI:        https://github.com/uzziellite
 * License:           GPL v2 or later
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define('API_BASE', 'http://172.105.37.163:5593/api/v1/wheels');


/**
 * Fetch data from a remote API
 * 
 * @param string $url The API endpoint URL
 * @return mixed The decoded JSON data or null on failure
 */
function fetchRemote($url) {
    $max_retries = 3;
    $retry_delay = 2; // seconds
    $attempt = 0;

    while ($attempt < $max_retries) {
        $response = wp_remote_get($url, array(
            'timeout' => 60,
            'headers' => array(
                'Accept' => 'application/json'
            )
        ));

        if (!is_wp_error($response)) {
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body);
            
            if (json_last_error() === JSON_ERROR_NONE && isset($data->data)) {
                return $data->data;
            } else {
                error_log("Tyres API JSON Error: Invalid JSON response from $url");
            }
        } else {
            $error_message = $response->get_error_message();
            error_log("Tyres API Error (Attempt " . ($attempt + 1) . "): $error_message for URL: $url");
        }

        $attempt++;
        if ($attempt < $max_retries) {
            sleep($retry_delay);
        }
    }

    echo "Tyres API Error: Failed to fetch data from $url after $max_retries attempts\n";

    return null;
}

/**
 * Get all the vehicles
 * 
 * @return array|null
 */
function getVehicles() :array|null {
	$url = API_BASE . '/brands';
	$vehicles = fetchRemote($url);
	$store = [];
	if(is_array($vehicles)){
		foreach($vehicles as $vehicle){
			array_push($store, [$vehicle->name,$vehicle->id,$vehicle->logo]);
		}

		return $store;
	}
}

/**
 * Get the vehicles model
 *  
 * @param string $id The id of the brand returned from the list of vehicles
 * @return array|null
 */
function getModel($id) :array|null {
	$url = API_BASE . "/models/". $id;
	$models = fetchRemote($url);
	$store = [];
	if(is_array($models)){
		foreach($models as $model){
			array_push($store, [$model->id,$model->name]);
		}

		return $store;
	}
}

/**
 * Get the vehicles Years of manufacturing
 * 
 * @param string $id The ID of the model
 * @return array|null
 */
function getYears($id): array|null {
	$url = API_BASE . "/years/" . $id;
	$years = fetchRemote($url);
    $store = [];
    if(is_array($years)){
    	foreach($years as $year){
    		array_push($store, [$year->id, $year->name]);
    	}

    	return $store;
    }
}


/**
 * Get the vehicles modifications
 * 
 * @param string $id The ID of the year
 * @return array|null
 */
function getModifications($id) :array|null {
	$url = API_BASE . "/modifications/" . $id;
	$modifications = fetchRemote($url);
	$store = [];
	if(is_array($modifications)){
		foreach($modifications as $modification){
			array_push($store, [$modification->id,$modification->name]);
		}
		return $store;
	}
}

/**
 * Get the vehicle tyres
 * 
 * @param string $id the id of the modification 
 * @return array|null
 */
function getAvailableTyres($id) :array|null {
	$url = API_BASE . "/tyres/" . $id;
	$tyres = fetchRemote($url);
	$store = [];
	if(is_array($tyres)){
		foreach($tyres as $tyre){
			array_push($store, [$tyre->id,$tyre->tyre]);
		}
		return $store;
	}
}

/**
 * Enqueue Select2 jQuery and CSS
 * @return void
 */
function silverstone_enqueue_select2_jquery() {
    wp_enqueue_style('select2-css', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css');
    
    wp_enqueue_script('select2-js', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js', array('jquery'), null, true);

    wp_enqueue_script(
		'select2-init',
		plugins_url( '/js/silverstone-select-2-init.js', __FILE__ ),
		array( 'jquery', 'select2-js' ),
		'1.0.0',
		array(
		   'in_footer' => true,
		)
	);

	 wp_enqueue_style(
        'silverstone-styles',
        plugins_url('/css/silverstone-styles.css', __FILE__),
        array(),
        '1.0.0'
    );
}
add_action('wp_enqueue_scripts', 'silverstone_enqueue_select2_jquery');

/**
 * Tyre Management Shortcode
 * This shortcode will display the vehicle selection form and results
 * @return string
 */
function silverstone_select2_shortcode() {
    $vehicles = getVehicles();
    ob_start();
    ?>
    <style>
    	#loader {
		    position: fixed;
		    top: 50%;
		    left: 50%;
		    transform: translate(-50%, -50%);
		    z-index: 9999;
		    background: rgba(0, 0, 0, 0.3);
		    padding: 20px;
		    border-radius: 10px;
		    animation: rotateAnimation 2s linear infinite;
		}

		@keyframes rotateAnimation {
	        from {
	            transform: rotate(0deg);
	        }
	        to {
	            transform: rotate(360deg);
	        }
	    }

        #tire-results {
            margin-top: 1.5rem;
            max-width: 400px;
            margin-left: auto;
            margin-right: auto;
        }

        #tire-results ul {
            list-style: none;
            padding: 0;
        }

        #tire-results li {
            padding: 0.75rem;
            background-color: #f9fafb;
            border: 1px solid #d1d5db;
            border-radius: 0.375rem;
            margin-bottom: 0.5rem;
            cursor: pointer;
            transition: background-color 0.2s ease;
        }

        #tire-results li:hover {
            background-color: #e5e7eb;
        }

        #tire-results li a {
            text-decoration: none;
            color: #1f2937;
            font-size: 1rem;
            display: block;
        }
    </style>
    <div class="mx-auto max-w-screen-xl px-4 py-16 sm:px-6 lg:px-8 silverstone-tyre-management">
    	<div id="loader" style="display: none; color: #fba41f;">
		    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="height:72px; width: 72px;">
			  <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 0 1 0-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28Z" />
			  <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
			</svg>
		</div>
	  <form id="silverstone_select_vehicles" action="" class="mx-auto mb-0 mt-8 max-w-md space-y-4">
	    <div style="margin-top: 1rem;">
	      <div class="relative">
	        <select id="ssbrand" class="w-full rounded-lg bg-gray-50 border border-gray-300 p-4 pe-12 text-sm shadow-lg">
	          <option selected disabled>Vehicle Brand</option>
	          <?php
	          	foreach($vehicles as $vehicle){
	          		echo "<option value='". htmlspecialchars($vehicle[1]) ."'>" . htmlspecialchars($vehicle[0]) . "</option>";
	          	}
	          ?>
	        </select>
	      </div>
	    </div>

	    <div style="margin-top: 1rem;">
	      <div class="relative">
	        <select id="ssmodel" class="w-full rounded-lg bg-gray-50 border border-gray-300 p-4 pe-12 text-sm shadow-lg" disabled>
	          <option selected disabled>Vehicle Model</option>
	        </select>
	      </div>
	    </div>

	    <div style="margin-top: 1rem;">
	      <div class="relative">
	        <select id="ssyear" class="w-full rounded-lg bg-gray-50 border border-gray-300 p-4 pe-12 text-sm shadow-lg" disabled>
	          <option selected disabled>Vehicle Year</option>
	        </select>
	      </div>
	    </div>

	    <div style="margin-top: 1rem;">
	      <div class="relative">
	        <select id="ssmodifications" class="w-full rounded-lg bg-gray-50 border border-gray-300 p-4 pe-12 text-sm shadow-lg" disabled>
	          <option selected disabled>Vehicle Modifications</option>
	        </select>
	      </div>
	    </div>
	  </form>
	  <div id="tire-results"></div>
	</div>

	<script>
		jQuery(document).ready(($) => {
			const fetchBrand = () => {
				const brand = $('#ssbrand').val();
				localStorage.setItem('selectedBrand', brand);
		        $('#ssmodel').prop('disabled', true);
		        $('#ssyear').prop('disabled', true);
		        $('#ssmodifications').prop('disabled', true);
		        $.ajax({
		            url: '<?php echo admin_url('admin-ajax.php'); ?>',
		            type: 'GET',
		            data: {
		                action: 'get_vehicle_models',
		                brand: brand
		            },
		            success: function(response) {
		                $('#ssmodel').html(response).prop('disabled', false);
		                fetchModel();
		            }
		        });
			}

			const fetchModel = () => {
				const id = $('#ssmodel').val();
		        $('#ssyear').prop('disabled', true);
		        $('#ssmodifications').prop('disabled', true);
		        $.ajax({
		            url: '<?php echo admin_url('admin-ajax.php'); ?>',
		            type: 'GET',
		            data: {
		                action: 'get_vehicle_years',
		                id		: id
		            },
		            success: function(response) {
		                $('#ssyear').html(response).prop('disabled', false);
		                fetchYear();
		            }
		        });
			}

			const fetchYear = () => {
				const id = $('#ssyear').val();
		        $('#ssmodifications').prop('disabled', true);
		        $.ajax({
		            url: '<?php echo admin_url('admin-ajax.php'); ?>',
		            type: 'GET',
		            data: {
		                action: 'get_vehicle_modifications',
		                id		: id
		            },
		            success: function(response) {
		                $('#ssmodifications').html(response).prop('disabled', false);
		                fetchModifications();
		            }
		        });
			}

			const fetchModifications = () => {
				const id = $('#ssmodifications').val();
		        $.ajax({
		            url: '<?php echo admin_url('admin-ajax.php'); ?>',
		            type: 'GET',
		            data: {
		                action: 'get_vehicle_tyres',
		                id		: id
		            },
		            success: function(response) {
		                let jsonString = response.replace(/'/g, '"');
		                let tireSizes = JSON.parse(jsonString);
		                displayTireSizes(tireSizes);
		            }
		        });
			}

			const generateWoocommerceSearchString = (tireSizesString) => {
				const createSearchStringFromString = (tireSizesStr) => {
				  	const tireSizes = tireSizesStr.replace(/[\[\]']+/g, "").split(', ');
				  	const baseUrl = "<?php echo home_url(); ?>?s=";
				  	const postType = "&post_type=product";
					const searchTerms = tireSizes.join("+").replace(/\//g, "%2F");
				  return `${baseUrl}${searchTerms}${postType}`;
				};
				const searchString = createSearchStringFromString(tireSizesString);
				return searchString;
			}

			const displayTireSizes = (tireSizes) => {
				const resultsContainer = $('#tire-results');
				resultsContainer.empty();
				if (tireSizes && tireSizes.length > 0 && !tireSizes.error) {
					const ul = $('<ul></ul>');
					tireSizes.forEach(tire => {
						const li = $('<li></li>').css({
							display: 'flex',
							alignItems: 'left',
							justifyContent: 'space-between',
							padding: '1rem'
						});
						const tireDetails = $('<div></div>').css({
							flex: '1'
						});
						tireDetails.append(`<strong>${tire.tire_full}</strong>`);
						tireDetails.append(`<br>Rim: ${tire.rim !== 'N/A' ? tire.rim : 'N/A'}`);
						tireDetails.append(`<br>Weight: ${tire.tire_weight_kg !== 'N/A' ? tire.tire_weight_kg + ' kg' : 'N/A'}`);
						tireDetails.append(`<br>Diameter: ${tire.tire_diameter_mm !== 'N/A' ? tire.tire_diameter_mm + ' mm' : 'N/A'}`);
						tireDetails.append(`<br>Bolt Pattern: ${tire.bolt_pattern}`);
						tireDetails.append(`<br>Fasteners: ${tire.wheel_fasteners_type} (${tire.wheel_fasteners_thread_size !== 'N/A' ? tire.wheel_fasteners_thread_size : 'N/A'})`);
						tireDetails.append(`<br>Tightening Torque: ${tire.wheel_tightening_torque !== 'N/A' ? tire.wheel_tightening_torque : 'N/A'}`);
						tireDetails.append(`<br>Pressure: ${tire.tire_pressure && tire.tire_pressure.bar ? `${tire.tire_pressure.bar} bar / ${tire.tire_pressure.kPa} kPa / ${tire.tire_pressure.psi} psi` : 'N/A'}`);
						if (!tire.product.available) {
							tireDetails.append(`<br><em style="color: #e53e3e;">Currently not available in store</em>`);
						}else{
							tireDetails.append(`<br><em style="color: #18e73b;">Available in store</em>`);
						}
						if (tire.image !== 'N/A') {
							const img = $('<img>').attr('src', tire.image).css({
								width: '200px',
								height: 'auto',
								marginRight: '1rem',
								borderRadius: '0.25rem'
							});
							tireDetails.append(img);
						}
						const link = $('<a></a>')
							.attr('href', tire.product.permalink)
							.css({
								pointerEvents: tire.product.available ? 'auto' : 'none',
								color: tire.product.available ? '#000' : '#1f2937',
								cursor: tire.product.available ? 'pointer' : 'default'
							})
							.append(tireDetails);
						const svg = $('<svg height="24px" width="24px" version="1.1" id="Layer_1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 512 512" xml:space="preserve">' +
							'<g>' +
							'<path style="fill:#535353;" d="M432.561,256.003c0,102.382-43.476,185.379-97.103,185.379s-97.103-82.997-97.103-185.379S281.83,70.623,335.457,70.623S432.561,153.62,432.561,256.003"/>' +
							'<path style="fill:#3B3D3F;" d="M250.122,142.11c-12.862,31.444-20.595,70.903-20.595,113.893c0,42.982,7.733,82.45,20.595,113.894c19.023-18.75,32.371-62.685,32.371-113.894C282.492,204.795,269.145,160.86,250.122,142.11"/>' +
							'<path style="fill:#535353;" d="M327.089,511.647c-83.862-7-150.528-118.775-150.528-255.647c0-136.942,66.737-248.417,150.669-255.629C324.115,0.132,320.963,0,317.803,0H194.217C96.707,0,17.665,114.617,17.665,256c0,141.391,79.042,256,176.552,256h123.586c7.821,0,15.51-0.812,23.066-2.242C336.323,510.614,331.742,511.294,327.089,511.647"/>' +
							'<g>' +
							'<path style="fill:#A4A4A4;" d="M335.457,441.382c-53.628,0-97.103-82.997-97.103-185.379S281.83,70.623,335.457,70.623s97.103,82.997,97.103,185.379S389.085,441.382,335.457,441.382 M494.327,254.414C493.753,118.345,419.99,7.453,327.239,0.373c-83.933,7.212-150.678,118.678-150.678,255.629c0,136.863,66.666,248.638,150.528,255.647c81.284-6.109,148.012-91.922,163.743-204.57c2.154-16.508,3.363-33.589,3.505-51.076C494.336,255.464,494.327,254.952,494.327,254.414"/>' +
							'<path style="fill:#A4A4A4;" d="M162.836,69.14c2.966,1.986,6.824,1.986,9.79,0l22.219-14.813l30.729,15.36c0.282,0.141,0.591,0.132,0.883,0.238c3.16-4.793,6.435-9.401,9.834-13.78c-0.759-0.9-1.695-1.686-2.816-2.251l-35.31-17.655c-2.834-1.412-6.215-1.201-8.845,0.556l-21.583,14.389l-21.592-14.389c-2.154-1.43-4.829-1.845-7.318-1.148l-49.09,14.027c-5.517,5.879-10.778,12.217-15.775,18.97c1.545,1.236,3.46,1.977,5.491,1.977c0.803,0,1.624-0.115,2.436-0.335l57.865-16.534L162.836,69.14z"/>' +
							'<path style="fill:#A4A4A4;" d="M127.526,130.933c2.966,1.986,6.824,1.986,9.79,0l22.219-14.813l30.729,15.36c1.271,0.636,2.622,0.936,3.946,0.936c0.759,0,1.51-0.115,2.242-0.309c1.571-4.555,3.213-9.039,4.943-13.427c-0.821-1.209-1.836-2.286-3.231-2.993l-35.31-17.655c-2.834-1.412-6.215-1.201-8.845,0.556l-21.583,14.389l-21.592-14.389c-2.966-1.986-6.824-1.986-9.79,0L79.46,112.978L57.867,98.589c-0.724-0.486-1.518-0.706-2.313-0.953c-2.71,4.979-5.332,10.063-7.786,15.334c0.124,0.088,0.185,0.221,0.309,0.309l26.483,17.655c2.966,1.986,6.824,1.986,9.79,0l21.592-14.389L127.526,130.933z"/>' +
							'<path style="fill:#A4A4A4;" d="M126.182,200.451c3.452,3.452,9.031,3.452,12.482,0l20.242-20.242l11.414,11.414c3.072,3.072,7.751,3.249,11.193,0.856c0.644-4.07,1.359-8.095,2.119-12.085c-0.291-0.415-0.459-0.883-0.830-1.254l-17.655-17.655c-3.452-3.452-9.031-3.452-12.482,0l-20.242,20.242l-20.242-20.242c-3.452-3.452-9.031-3.452-12.482,0l-20.242,20.242l-20.242-20.242c-1.668-1.668-4.034-2.507-6.294-2.586c-2.357,0.018-4.617,0.971-6.259,2.657l-21.857,22.369c-1.139,5.632-2.145,11.352-3.019,17.143c1.58,1.227,3.416,1.969,5.314,1.969c2.295,0,4.59-0.892,6.321-2.657l19.624-20.092l20.171,20.162c3.452,3.452,9.031,3.452,12.482,0l20.242-20.242L126.182,200.451z"/>' +
							'<path style="fill:#A4A4A4;" d="M176.561,256.003c0-0.486,0.018-0.971,0.026-1.457l-39.618-23.764c-3.478-2.083-7.918-1.527-10.787,1.324l-20.242,20.242l-20.242-20.242c-3.452-3.452-9.031-3.452-12.482,0l-21.981,21.981L21.61,239.274c-1.121-0.556-2.304-0.839-3.487-0.9c-0.274,5.835-0.459,11.697-0.459,17.629c0,0.353,0.018,0.697,0.018,1.05l31.347,15.678c3.407,1.713,7.512,1.033,10.187-1.66l20.242-20.242l20.242,20.242c3.452,3.452,9.031,3.452,12.482,0l21.61-21.61l38.223,22.934c1.421,0.856,2.993,1.262,4.537,1.262c0.141,0,0.282-0.044,0.424-0.053C176.728,267.779,176.561,261.926,176.561,256.003"/>' +
							'<path style="fill:#A4A4A4;" d="M180.528,312.775l-16.728-11.149c-3.513-2.348-8.166-1.871-11.132,1.103l-20.242,20.242l-20.242-20.242c-3.452-3.452-9.039-3.452-12.482,0L79.461,322.97l-20.242-20.242c-1.871-1.871-4.467-2.798-7.115-2.542c-2.631,0.256-5.005,1.686-6.471,3.884l-17.655,26.483c-1.254,1.88-1.607,4.052-1.315,6.117c0.088,0.388,0.177,0.777,0.274,1.165c0.547,1.951,1.668,3.752,3.487,4.961c1.501,0.998,3.204,1.483,4.89,1.483c2.851,0,5.65-1.377,7.353-3.928l11.679-17.532l18.873,18.873c3.443,3.452,9.031,3.452,12.482,0l20.242-20.242l20.242,20.242c3.443,3.452,9.031,3.452,12.482,0l21.363-21.363l20.462,13.639c1.201,0.794,2.534,1.165,3.884,1.315C182.885,327.923,181.605,320.41,180.528,312.775"/>' +
							'<path style="fill:#A4A4A4;" d="M206.377,405.313c-2.931-6.568-5.676-13.365-8.227-20.383l-26.465-13.241c-3.99-1.977-8.828-0.697-11.299,3.001l-11.679,17.532l-18.873-18.873c-3.443-3.452-9.031-3.452-12.482,0L97.11,393.59l-20.242-20.242c-3.443-3.452-9.031-3.452-12.482,0l-17.655,17.655c-0.671,0.671-1.095,1.465-1.51,2.269c1.863,4.228,3.796,8.369,5.809,12.42c2.86,0.653,5.959,0.018,8.183-2.207l11.414-11.414l20.242,20.242c3.452,3.452,9.039,3.452,12.482,0l20.242-20.242l20.242,20.242c1.668,1.66,3.911,2.586,6.241,2.586c0.291,0,0.583-0.018,0.874-0.044c2.631-0.256,5.005-1.686,6.471-3.884l13.338-20.003l28.337,14.168C201.478,406.328,204.1,406.249,206.377,405.313"/>' +
							'<path style="fill:#A4A4A4;" d="M207.94,475.209l26.483-17.655c0.706-0.468,1.201-1.112,1.721-1.73c-3.363-4.352-6.612-8.916-9.746-13.683c-0.591,0.23-1.218,0.353-1.766,0.724l-21.592,14.389l-21.583-14.389c-2.966-1.986-6.824-1.986-9.79,0l-21.592,14.389l-21.583-14.389c-2.966-1.986-6.824-1.986-9.79,0L92.219,460.52c-0.883,0.591-1.562,1.351-2.154,2.154c4.043,4.29,8.201,8.377,12.491,12.164l21.036-14.018l21.592,14.389c2.966,1.986,6.824,1.986,9.79,0l21.583-14.389l21.592,14.389C201.116,477.195,204.974,477.195,207.94,475.209"/>' +
							'</g></g></svg>').css({
							marginLeft: '0.5rem',
							flexShrink: '0'
						});
						li.append(link).append(svg);
						ul.append(li);
					});
					resultsContainer.append(ul);
				} else {
					resultsContainer.append('<p>No tyres found for this vehicle!</p>');
				}
			}

		    $('#ssbrand').change(() => {
		        fetchBrand();
		    });

		    $('#ssmodel').change(() => {
		        fetchModel();
		    });

		    $('#ssyear').change(() => {
		        fetchYear();
		    });

		    $('#ssmodifications').change(() => {
		        fetchModifications();
		    });

		    $('#ss_search').click((event) => {
		        event.preventDefault();
		        fetchModifications();
		    });

		    $(document).ajaxStart(() => {
		        $('#loader').show();
		    });
		    
		    $(document).ajaxStop(() => {
		        $('#loader').hide();
		    });

			if( localStorage.getItem('selectedBrand') ){
				localStorage.removeItem('selectedBrand');
				window.location.reload();
			}
		});
	</script>
    <?php
    return ob_get_clean();
}
add_shortcode('silverstone_vehicles_brand', 'silverstone_select2_shortcode');

/**
 * Fetch vehicle brands from the API
 * @return string
 */
add_action('wp_ajax_get_vehicle_models', 'get_vehicle_models_callback');
add_action('wp_ajax_nopriv_get_vehicle_models', 'get_vehicle_models_callback');
function get_vehicle_models_callback() {
    $brand = sanitize_text_field($_GET['brand']);
    
    $models = getModel($brand);

    if (!empty($models)) {
        foreach ($models as $model) {
            
            echo '<option value="' . esc_attr($model[0]) . '">' . esc_html($model[1]) . '</option>';
        }
    } else {
        echo '<option selected disabled>No models found</option>';
    }
    wp_die();
}

/**
 * Fetch vehicle years from the API
 * @return string
 */
add_action('wp_ajax_get_vehicle_years', 'get_vehicle_years_callback');
add_action('wp_ajax_nopriv_get_vehicle_years', 'get_vehicle_years_callback');
function get_vehicle_years_callback() {
    $id = sanitize_text_field($_GET['id']);
    
    
    $years = getYears($id);

    if (!empty($years)) {
        foreach ($years as $year) {
            
            echo '<option value="' . esc_attr($year[0]) . '">' . esc_html($year[1]) . '</option>';
        }
    } else {
        echo '<option selected disabled>No years found</option>';
    }
    wp_die();
}

/**
 * Fetch vehicle modifications from the API
 * @return string
 */
add_action('wp_ajax_get_vehicle_modifications', 'get_vehicle_modifications_callback');
add_action('wp_ajax_nopriv_get_vehicle_modifications', 'get_vehicle_modifications_callback');
function get_vehicle_modifications_callback() {
    $id = sanitize_text_field($_GET['id']);
    
    $modifications = getModifications($id);

    if (!empty($modifications)) {
        foreach ($modifications as $modification) {
            
            echo '<option value="' . esc_attr($modification[0]) . '">' . esc_html($modification[1]) . '</option>';
        }
    } else {
        echo '<option selected disabled>No Modifications Found</option>';
    }
    wp_die();
}

/**
 * Fetch vehicle tyres from the API
 * This function retrieves detailed tyre information for a specific vehicle modification.
 * It checks if the tyre size matches a product in WooCommerce and returns the details.
 * 
 * @return array
 */
add_action('wp_ajax_get_vehicle_tyres', 'get_vehicle_tyres_callback');
add_action('wp_ajax_nopriv_get_vehicle_tyres', 'get_vehicle_tyres_callback');
function get_vehicle_tyres_callback() {
    $id = sanitize_text_field($_GET['id']);
    
    $tyres = getAvailableTyres($id);
    $detailed_tyres = [];

    if (!empty($tyres)) {
        foreach ($tyres as $tyre) {
            // Fetch detailed tire data from the API response
            $url = API_BASE . "/tyres/" . $id;
            $response = wp_remote_get($url);
            
            if (!is_wp_error($response)) {
                $body = wp_remote_retrieve_body($response);
                $api_data = json_decode($body, true)['data'];
                
                foreach ($api_data as $item) {
                    foreach ($item['data_response'] as $data) {
                        foreach ($data['wheels'] as $wheel) {
                            $front = $wheel['front'];
                            $technical = $data['technical'];
                            $tire_size = $front['tire_full'] ?? 'N/A';
							$image_url = $data['generation']['bodies'][0]['image'] ?? 'N/A';
                            
                            
                            $core_tire_size = 'N/A';
							if ($tire_size !== 'N/A' && preg_match('/^(\d+\/\d+R\d+)/', $tire_size, $matches)) {
								$core_tire_size = $matches[1];
							}
                            
                            
                            $product_data = [];
                            if ($core_tire_size !== 'N/A') {
                                $args = [
                                    'post_type' => 'product',
                                    'post_status' => 'publish',
                                    'posts_per_page' => 1,
                                    's' => $core_tire_size
                                ];
                                $query = new WP_Query($args);
                                
                                if ($query->have_posts()) {
                                    while ($query->have_posts()) {
                                        $query->the_post();
                                        $product_id = get_the_ID();
                                        $product_data = [
                                            'product_id' => $product_id,
                                            'permalink' => get_permalink($product_id),
                                            'available' => true
                                        ];
                                    }
                                    wp_reset_postdata();
                                } else {
                                    $product_data = [
                                        'product_id' => null,
                                        'permalink' => '#',
                                        'available' => false
                                    ];
                                }
                            } else {
                                $product_data = [
                                    'product_id' => null,
                                    'permalink' => '#',
                                    'available' => false
                                ];
                            }
                            
                            $detailed_tyres[] = [
                                'tire_full' => $tire_size,
                                'tire_weight_kg' => $front['tire_weight_kg'] ?? 'N/A',
                                'tire_diameter_mm' => $front['tire_diameter_mm'] ?? 'N/A',
                                'bolt_pattern' => $technical['bolt_pattern'] ?? 'N/A',
                                'wheel_fasteners_type' => $technical['wheel_fasteners']['type'] ?? 'N/A',
                                'wheel_fasteners_thread_size' => $technical['wheel_fasteners']['thread_size'] ?? 'N/A',
                                'tire_pressure' => $front['tire_pressure'] ?? 'N/A',
                                'rim' => $front['rim'] ?? 'N/A',
                                'wheel_tightening_torque' => $technical['wheel_tightening_torque'] ?? 'N/A',
                                'product' => $product_data,
								'image' => $image_url
                            ];
                        }
                    }
                }
            }
        }
        
        echo json_encode($detailed_tyres);
    } else {
        echo json_encode(['error' => 'No tyres found for this vehicle!']);
    }
    wp_die();
}

/**
 * Register the REST API endpoint for fetching tyres
 * This endpoint allows clients to retrieve tyre information via a POST request.
 */
add_action('rest_api_init', function () {
    register_rest_route('tyres/v1', '/get', array(
        'methods' => WP_REST_Server::CREATABLE,
        'callback' => 'get_tyres',
        'permission_callback' => '__return_true',
    ));
});


/**
 * Return the tyres based on the request
 * This function is called when the REST API endpoint is hit.
 * @return WP_REST_Response|WP_Error
 * @throws WP_Error if the request body is invalid or if there are no tyres found
 */
function get_tyres($request) {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('Request Body: ' . print_r($request->get_body(), true));
    }

    $body = json_decode($request->get_body(), true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        return new WP_Error('invalid_json', 'Invalid JSON in request body', array('status' => 400));
    }
}