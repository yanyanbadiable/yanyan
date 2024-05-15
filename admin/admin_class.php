<?php
session_start();
ini_set('display_errors', 1);
class Action
{
	private $db;

	public function __construct()
	{
		ob_start();
		include 'db_connect.php';

		$this->db = $conn;
	}
	function __destruct()
	{
		$this->db->close();
		ob_end_flush();
	}

	function login()
	{

		$username = $_POST['username'];
		$password = $_POST['password'];

		// Prepare the SQL query
		$stmt = $this->db->prepare("SELECT * FROM users WHERE username = ?");
		$stmt->bind_param("s", $username);
		$stmt->execute();
		$result = $stmt->get_result();

		// Check if user exists
		if ($result->num_rows > 0) {
			$user = $result->fetch_assoc();

			if (password_verify($password, $user['password'])) {
				// Store user data in session, excluding password
				foreach ($user as $key => $value) {
					if ($key !== 'password') {
						$_SESSION['login_' . $key] = $value;
					}
				}

				// Check user type
				if ($_SESSION['login_type'] != 0) {
					// Unset session variables and return status 2 for non-privileged users
					session_unset();
					return 2;
				}

				// Return status 1 for successful login
				return 1;
			}
		}

		// Return status 3 for invalid credentials
		return 3;
	}
	function login_faculty()
	{

		extract($_POST);
		$qry = $this->db->query("SELECT *,concat(lastname,', ',firstname,' ',middlename) as name FROM faculty where id_no = '" . $id_no . "' ");
		if ($qry->num_rows > 0) {
			foreach ($qry->fetch_array() as $key => $value) {
				if ($key != 'password' && !is_numeric($key))
					$_SESSION['login_' . $key] = $value;
			}
			return 1;
		} else {
			return 3;
		}
	}
	function login2()
	{

		extract($_POST);
		if (isset($email))
			$username = $email;
		$qry = $this->db->query("SELECT * FROM users where username = '" . $username . "' and password = '" . md5($password) . "' ");
		if ($qry->num_rows > 0) {
			foreach ($qry->fetch_array() as $key => $value) {
				if ($key != 'password' && !is_numeric($key))
					$_SESSION['login_' . $key] = $value;
			}
			if ($_SESSION['login_alumnus_id'] > 0) {
				$bio = $this->db->query("SELECT * FROM alumnus_bio where id = " . $_SESSION['login_alumnus_id']);
				if ($bio->num_rows > 0) {
					foreach ($bio->fetch_array() as $key => $value) {
						if ($key != 'password' && !is_numeric($key))
							$_SESSION['bio'][$key] = $value;
					}
				}
			}
			if ($_SESSION['bio']['status'] != 1) {
				foreach ($_SESSION as $key => $value) {
					unset($_SESSION[$key]);
				}
				return 2;
				exit;
			}
			return 1;
		} else {
			return 3;
		}
	}
	function logout()
	{
		session_destroy();
		foreach ($_SESSION as $key => $value) {
			unset($_SESSION[$key]);
		}
		header("location:login.php");
	}
	function logout2()
	{
		session_destroy();
		foreach ($_SESSION as $key => $value) {
			unset($_SESSION[$key]);
		}
		header("location:../index.php");
	}

	function save_user()
	{
		if ($_SERVER['REQUEST_METHOD'] === 'POST') {
			// Retrieve form data
			$username = $_POST['username'];
			$firstname = $_POST['firstname'];
			$middlename = $_POST['middlename'];
			$lastname = $_POST['lastname'];
			$extensionname = $_POST['extensionname'];
			$password = password_hash($_POST['password'], PASSWORD_DEFAULT);
			$program_id = $_POST['program_id'];
			$type = 1;

			// Insert data into the database using prepared statements
			$query = "INSERT INTO users (username, fname, mname, lname, extname, password, type, program_id) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

			$stmt = mysqli_prepare($this->db, $query);

			// Bind parameters
			mysqli_stmt_bind_param($stmt, "ssssssii", $username, $firstname, $middlename, $lastname, $extensionname, $password, $type, $program_id);

			// Execute statement
			if (mysqli_stmt_execute($stmt)) {
				// Insertion successful
				echo '1'; // Return '1' to indicate success to the client-side JavaScript
			} else {
				// Insertion failed
				echo 'Error: ' . mysqli_stmt_error($stmt); // Return error message to the client-side JavaScript
			}

			// Close statement
			mysqli_stmt_close($stmt);
		}
	}

	function edit_user()
	{
		if ($_SERVER['REQUEST_METHOD'] === 'POST') {
			// Get data from the POST request
			$idno = $_POST['idno'];
			$firstname = $_POST['firstname'];
			$middlename = $_POST['middlename'];
			$lastname = $_POST['lastname'];
			$extensionname = $_POST['extensionname'];
			$email = $_POST['email'];
			$program_id = $_POST['program_id'];
			$password = $_POST['password'];

			$query = "SELECT * FROM users WHERE idno = '$idno'";
			$result = mysqli_query($this->db, $query);

			// Check if user exists
			if (mysqli_num_rows($result) > 0) {
				// Update user details
				$update_query = "UPDATE users SET fname = '$firstname', mname = '$middlename', lname = '$lastname', extname = '$extensionname', type = 1, program_id = '$program_id', email = '$email', password = '" . password_hash($password, PASSWORD_DEFAULT) . "' WHERE idno = '$idno'";

				if (mysqli_query($this->db, $update_query)) {
					echo "User updated successfully.";
				} else {
					echo "Error updating user: " . mysqli_error($this->db);
				}
			} else {
				echo "User not found.";
			}
		}
	}

	function delete_user()
	{
		extract($_POST);
		$delete = $this->db->query("DELETE FROM users where id = " . $id);
		if ($delete)
			return 1;
	}
	function signup()
	{
		extract($_POST);
		$data = " name = '" . $firstname . ' ' . $lastname . "' ";
		$data .= ", username = '$email' ";
		$data .= ", password = '" . md5($password) . "' ";
		$chk = $this->db->query("SELECT * FROM users where username = '$email' ")->num_rows;
		if ($chk > 0) {
			return 2;
			exit;
		}
		$save = $this->db->query("INSERT INTO users set " . $data);
		if ($save) {
			$uid = $this->db->insert_id;
			$data = '';
			foreach ($_POST as $k => $v) {
				if ($k == 'password')
					continue;
				if (empty($data) && !is_numeric($k))
					$data = " $k = '$v' ";
				else
					$data .= ", $k = '$v' ";
			}
			if ($_FILES['img']['tmp_name'] != '') {
				$fname = strtotime(date('y-m-d H:i')) . '_' . $_FILES['img']['name'];
				$move = move_uploaded_file($_FILES['img']['tmp_name'], 'assets/uploads/' . $fname);
				$data .= ", avatar = '$fname' ";
			}
			$save_alumni = $this->db->query("INSERT INTO alumnus_bio set $data ");
			if ($data) {
				$aid = $this->db->insert_id;
				$this->db->query("UPDATE users set alumnus_id = $aid where id = $uid ");
				$login = $this->login2();
				if ($login)
					return 1;
			}
		}
	}
	function update_account()
	{
		extract($_POST);
		$data = " name = '" . $firstname . ' ' . $lastname . "' ";
		$data .= ", username = '$email' ";
		if (!empty($password))
			$data .= ", password = '" . md5($password) . "' ";
		$chk = $this->db->query("SELECT * FROM users where username = '$email' and id != '{$_SESSION['login_id']}' ")->num_rows;
		if ($chk > 0) {
			return 2;
			exit;
		}
		$save = $this->db->query("UPDATE users set $data where id = '{$_SESSION['login_id']}' ");
		if ($save) {
			$data = '';
			foreach ($_POST as $k => $v) {
				if ($k == 'password')
					continue;
				if (empty($data) && !is_numeric($k))
					$data = " $k = '$v' ";
				else
					$data .= ", $k = '$v' ";
			}
			if ($_FILES['img']['tmp_name'] != '') {
				$fname = strtotime(date('y-m-d H:i')) . '_' . $_FILES['img']['name'];
				$move = move_uploaded_file($_FILES['img']['tmp_name'], 'assets/uploads/' . $fname);
				$data .= ", avatar = '$fname' ";
			}
			$save_alumni = $this->db->query("UPDATE alumnus_bio set $data where id = '{$_SESSION['bio']['id']}' ");
			if ($data) {
				foreach ($_SESSION as $key => $value) {
					unset($_SESSION[$key]);
				}
				$login = $this->login2();
				if ($login)
					return 1;
			}
		}
	}

	function save_settings()
	{
		extract($_POST);
		$data = " name = '" . str_replace("'", "&#x2019;", $name) . "' ";
		$data .= ", email = '$email' ";
		$data .= ", contact = '$contact' ";
		$data .= ", about_content = '" . htmlentities(str_replace("'", "&#x2019;", $about)) . "' ";
		if ($_FILES['img']['tmp_name'] != '') {
			$fname = strtotime(date('y-m-d H:i')) . '_' . $_FILES['img']['name'];
			$move = move_uploaded_file($_FILES['img']['tmp_name'], 'assets/uploads/' . $fname);
			$data .= ", cover_img = '$fname' ";
		}

		// echo "INSERT INTO system_settings set ".$data;
		$chk = $this->db->query("SELECT * FROM system_settings");
		if ($chk->num_rows > 0) {
			$save = $this->db->query("UPDATE system_settings set " . $data);
		} else {
			$save = $this->db->query("INSERT INTO system_settings set " . $data);
		}
		if ($save) {
			$query = $this->db->query("SELECT * FROM system_settings limit 1")->fetch_array();
			foreach ($query as $key => $value) {
				if (!is_numeric($key))
					$_SESSION['settings'][$key] = $value;
			}

			return 1;
		}
	}

	function save_building()
	{

		extract($_POST);
		$data = "building = '$building', ";
		$data .= "department_id = '$department_id'";

		if (empty($id)) {
			$save = $this->db->query("INSERT INTO building set $data");
		} else {
			$save = $this->db->query("UPDATE building set $data where id = $id");
		}
		if ($save)
			return 1;
	}

	function delete_building()
	{
		extract($_POST);
		$delete = $this->db->query("DELETE FROM building where id = " . $id);
		if ($delete) {
			return 1;
		}
	}

	function save_department()
	{
		extract($_POST);
		$data = " department_code = '$department_code' ";
		$data .= ", department_name = '$department_name' ";

		if (empty($id)) {
			$save = $this->db->query("INSERT INTO department set $data");
		} else {
			$save = $this->db->query("UPDATE department set $data where id = $id");
		}
		if ($save)
			return 1;
	}
	function delete_department()
	{
		extract($_POST);
		$delete = $this->db->query("DELETE FROM department where id = " . $id);
		if ($delete) {
			return 1;
		}
	}

	function save_course()
	{
		$res = 1;
		extract($_POST);
		$stmt = $this->db->prepare("INSERT INTO courses (year, period, level, program_id, course_code, course_name, lec, lab, units, is_comlab) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
		$stmt->bind_param("ssssssdddd", $year_val, $period_val, $level_val, $program_id_val, $course_code_val, $course_name_val, $lec_val, $lab_val, $units_val, $is_comlab_val);

		foreach ($year as $key => $value) {
			if (array_key_exists($key, $year)) {
				// Set values for each iteration
				$year_val = date("Y");
				$period_val = $period[$key];
				$level_val = $level[$key];
				$program_id_val = $program_id[$key];
				$course_code_val = $course_code[$key];
				$course_name_val = $course_name[$key];
				$lec_val = $lec[$key];
				$lab_val = $lab[$key];
				$units_val = $units[$key];
				$is_comlab_val = $is_comlab[$key];

				// Check if the course already exists in the database
				$check_stmt = $this->db->prepare("SELECT COUNT(*) FROM courses WHERE course_code = ? AND course_name = ?");
				$check_stmt->bind_param("ss", $course_code_val, $course_name_val);
				$check_stmt->execute();
				$check_stmt->bind_result($count);
				$check_stmt->fetch();
				$check_stmt->close();

				// var_dump($count);

				// If course already exists, set $res to 0 and skip insertion
				if ($count > 0) {
					$res = 0;
					continue; // Skip insertion
				}

				// Execute the insert query
				$save = $stmt->execute();

				// If insertion fails, set $res to 0
				if (!$save) {
					$res = 0;
				}
			}
		}

		$stmt->close();

		return $res;
	}



	function edit_course()
	{
		// Assuming this function is within a class context and $this->db represents your database connection

		if (isset($_POST['course_id'], $_POST['course_code'], $_POST['course_name'], $_POST['lec'], $_POST['lab'], $_POST['units'], $_POST['comlab'], $_POST['program_code'], $_POST['year'])) {
			$course_id = $_POST['course_id'];

			// Retrieve form data from POST    
			$course_code = $_POST['course_code'];
			$course_name = $_POST['course_name'];
			$lec = $_POST['lec'];
			$lab = $_POST['lab'];
			$units = $_POST['units'];
			$is_comlab = $_POST['comlab'];
			$program_code = $_POST['program_code'];
			$year = $_POST['year'];

			// Prepare and execute the update query
			$update_query = "UPDATE courses SET course_code = ?, course_name = ?, lec = ?, lab = ?, units = ?, is_comlab = ? WHERE id = ?";
			$update_stmt = $this->db->prepare($update_query);
			$update_stmt->bind_param("ssdddsi", $course_code, $course_name, $lec, $lab, $units, $is_comlab, $course_id); // Use 'i' for integer, 'd' for double/float

			// Execute the statement
			$update_result = $update_stmt->execute();

			if ($update_result) {
				// Send response of "1" for success
				return 1;
			} else {
				// Send response of "0" for failure
				return 0;
			}
		} else {
			// Send response of 0 for missing parameters
			return 0;
		}
	}




	function delete_course()
	{
		extract($_POST);
		$delete = $this->db->query("DELETE FROM courses where id = " . $id);
		if ($delete) {
			return 1;
		}
	}

	function save_room()
	{
		extract($_POST);

		// Check if room already exists
		$existing_room = $this->db->query("SELECT id FROM rooms WHERE room = '$room' AND building_id = '$building_id'")->fetch_assoc();

		// If room does not exist, insert it
		if (empty($id)) {
			// If room does not exist, insert it
			if (!$existing_room) {
				$data = "room = '$room', ";
				$data .= "description = '$description', ";
				$data .= "building_id = '$building_id' ";
				$save = $this->db->query("INSERT INTO rooms SET $data");
				return 1;
			} else {
				// Room already exists, return error code
				return 0;
			}
		} else {
			// If updating existing room, ensure it's not a duplicate
			if (empty($existing_room) || $existing_room['id'] == $id) {
				$data = "room = '$room', ";
				$data .= "description = '$description', ";
				$data .= "building_id = '$building_id' ";
				$save = $this->db->query("UPDATE rooms SET $data WHERE id = $id");
				return 2;
			} else {
				// Room already exists with different ID, return error code
				return 0;
			}
		}
	}

	function delete_room()
	{
		extract($_POST);
		$delete = $this->db->query("DELETE FROM rooms where id = " . $id);
		if ($delete) {
			return 1;
		}
	}

	function save_section()
	{
		extract($_POST);
		$data = "program_id = '$program_id', ";
		$data .= "level = '$level', ";
		$data .= "section_name = '$section_name' ";

		// Check if section already exists
		$existing_section = $this->db->query("SELECT id FROM sections WHERE program_id = '$program_id' AND level = '$level' AND section_name = '$section_name'")->fetch_assoc();

		// // Debugging: Output existing section data
		// var_dump($existing_section);

		if (empty($id)) {
			// If section does not exist, insert it
			if (!$existing_section) {
				$save = $this->db->query("INSERT INTO sections SET $data");
				return 1;
			} else {
				// Section already exists, return error code
				return 0;
			}
		} else {
			// If updating existing section, ensure it's not a duplicate
			if (empty($existing_section) || $existing_section['id'] == $id) {
				$save = $this->db->query("UPDATE sections SET $data WHERE id = $id");
				return 2;
			} else {
				// Section already exists with different ID, return error code
				return 0;
			}
		}
	}



	function delete_section()
	{
		extract($_POST);
		$delete = $this->db->query("DELETE FROM sections where id = " . $id);
		if ($delete) {
			return 1;
		}
	}

	function save_faculty()
	{
		if ($_SERVER['REQUEST_METHOD'] === 'POST') {
			// Retrieve form data
			$program_id = $_POST['program_id'];
			$gender = $_POST['gender'];
			$designation = $_POST['designation'];
			$street = $_POST['street'];
			$barangay = $_POST['barangay'];
			$municipality = $_POST['municipality'];
			$province = $_POST['province'];
			$contact = $_POST['contact'];
			$email = $_POST['email'];

			// Query to get the latest ID from the users table
			$latestUserIdQuery = "SELECT id FROM users ORDER BY id DESC LIMIT 1";

			// Execute the query to get the latest user ID
			$latestUserIdResult = mysqli_query($this->db, $latestUserIdQuery);

			if ($latestUserIdResult && mysqli_num_rows($latestUserIdResult) > 0) {
				$row = mysqli_fetch_assoc($latestUserIdResult);
				$latestUserId = $row['id'];
			} else {
				echo "No users found";
				return; // Exit the function if no user found
			}

			// Insert data into the database using prepared statements
			$insertQuery = "INSERT INTO faculty (program_id, gender, designation, street, barangay, municipality, province, contact, email, user_id) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

			$stmt = mysqli_prepare($this->db, $insertQuery);

			// Bind parameters
			mysqli_stmt_bind_param($stmt, "issssssssi", $program_id, $gender, $designation, $street, $barangay, $municipality, $province, $contact, $email, $latestUserId);

			// Execute statement
			if (mysqli_stmt_execute($stmt)) {
				// Insertion successful
				echo '1'; // Return '1' to indicate success to the client-side JavaScript
			} else {
				// Insertion failed
				echo 'Error: ' . mysqli_stmt_error($stmt); // Return error message to the client-side JavaScript
			}

			// Close statement
			mysqli_stmt_close($stmt);
		}
	}

	function edit_faculty()
	{
	}


	function delete_faculty()
	{
		extract($_POST);
		$delete = $this->db->query("DELETE FROM faculty where id = " . $id);
		if ($delete) {
			return 1;
		}
	}
	function save_schedule()
	{
		extract($_POST);
		$data = " faculty_id = '$faculty_id' ";
		$data .= ", subject_id = '$subject_id' ";
		$data .= ", schedule_type = '$schedule_type' ";
		$data .= ", room_id = '$room_id' ";
		if (isset($is_repeating)) {
			$data .= ", is_repeating = '$is_repeating' ";
			$rdata = array('dow' => implode(',', $dow), 'start' => $month_from . '-01', 'end' => (date('Y-m-d', strtotime($month_to . '-01 +1 month - 1 day '))));
			$data .= ", repeating_data = '" . json_encode($rdata) . "' ";
		} else {
			$data .= ", is_repeating = 0 ";
			$data .= ", schedule_date = '$schedule_date' ";
		}
		$data .= ", time_from = '$time_from' ";
		$data .= ", time_to = '$time_to' ";

		if (empty($id)) {
			$save = $this->db->query("INSERT INTO schedules set " . $data);
		} else {
			$save = $this->db->query("UPDATE schedules set " . $data . " where id=" . $id);
		}
		if ($save)
			return 1;
	}
	function delete_schedule()
	{
		extract($_POST);
		$delete = $this->db->query("DELETE FROM schedules where id = " . $id);
		if ($delete) {
			return 1;
		}
	}
	function get_schedule()
	{
		// if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['course_offering_info_id'])) {
		// 	$course_offering_info_id = $_GET['course_offering_info_id'];
		// 	$event_array = array();

		// 	$schedules_result = $this->db->query("SELECT * FROM schedules WHERE course_offering_info_id = $course_offering_info_id");
		// 	while ($sched = $schedules_result->fetch_assoc()) {
		// 		// Query to get course detail
		// 		$course_detail_result = $this->db->query("SELECT courses.course_code, course_offering_info.section_id
		//                                               FROM courses 
		//                                               JOIN course_offering_info ON course_offering_info.courses_id = courses.id 
		//                                               WHERE course_offering_info.id = $course_offering_info_id");
		// 		if (!$course_detail_result) {
		// 			throw new Exception("Error fetching course detail information: " . $this->db->error);
		// 		}
		// 		$course_detail = $course_detail_result->fetch_assoc();

		// 		// Determine day and color
		// 		$day = '';
		// 		$color = '';
		// 		switch ($sched['day']) {
		// 			case 'M':
		// 				$day = 'Monday';
		// 				$color = 'LightSalmon';
		// 				break;
		// 			case 'T':
		// 				$day = 'Tuesday';
		// 				$color = 'lightblue';
		// 				break;
		// 			case 'W':
		// 				$day = 'Wednesday';
		// 				$color = 'LightSalmon';
		// 				break;
		// 			case 'Th':
		// 				$day = 'Thursday';
		// 				$color = 'lightblue';
		// 				break;
		// 			case 'F':
		// 				$day = 'Friday';
		// 				$color = 'LightSalmon';
		// 				break;
		// 			case 'Sa':
		// 				$day = 'Saturday';
		// 				$color = 'lightblue';
		// 				break;
		// 			case 'Su':
		// 				$day = 'Sunday';
		// 				$color = 'LightSalmon';
		// 				break;
		// 		}

		// 		// Add event to event array
		// 		$event_array[] = array(
		// 			'id' => $sched['id'],
		// 			'title' => $course_detail['course_code'] . '<br>' . $sched['room_id'] . '<br>' . $course_detail['section_id'],
		// 			'start' => date('Y-m-d', strtotime('this ' . $day)) . 'T' . $sched['time_start'],
		// 			'end' => date('Y-m-d', strtotime('this ' . $day)) . 'T' . $sched['time_end'],
		// 			'color' => $color,
		// 			"textEscape" => 'false',
		// 			'textColor' => 'black',
		// 			'course_offering_info_id' => $course_offering_info_id
		// 		);
		// 	}
		// 	echo json_encode($event_array);
		// }
	}


	function add_course_offer()
	{
		if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['section_id']) && isset($_GET['course_id'])) {
			$section_id = $_GET['section_id'];
			$course_id = $_GET['course_id'];

			// Query to check if the offering already exists
			$check_query = $this->db->prepare("SELECT * FROM course_offering_info WHERE courses_id = ? AND section_id = ?");
			$check_query->bind_param("ss", $course_id, $section_id);
			$check_query->execute();
			$check_result = $check_query->get_result();

			// Fetch the curriculum details
			$course_query = $this->db->prepare("SELECT level FROM courses WHERE id = ?");
			$course_query->bind_param("s", $course_id);
			$course_query->execute();
			$course_result = $course_query->get_result();
			$course_row = $course_result->fetch_assoc();
			$level = $course_row['level'];

			// If the offering doesn't exist, add it to the database
			if ($check_result->num_rows === 0) {
				$offering_query = $this->db->prepare("INSERT INTO course_offering_info (courses_id, section_id) VALUES (?, ?)");
				$offering_query->bind_param("ss", $course_id, $section_id);
				$offering_query->execute();
				echo 'Offered Subject!';
			} else {
				echo 'Offered Subject Already Exists!';
			}
		}
	}

	function remove_course_offering()
	{
		if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['section_id']) && isset($_GET['courses_id'])) {
			$section_id = $_GET['section_id'];
			$courses_id = $_GET['courses_id'];

			// Prepare and execute SELECT query
			$check_if_exists_query = "SELECT * FROM course_offering_info WHERE courses_id = ? AND section_id = ? LIMIT 1";
			$stmt = $this->db->prepare($check_if_exists_query);
			if (!$stmt) {
				echo 'Error preparing query: ' . $this->db->error;
				return;
			}
			$stmt->bind_param("ii", $courses_id, $section_id);
			$stmt->execute();
			$result = $stmt->get_result();

			$check_if_exists = $result->fetch_assoc();


			if ($check_if_exists) {
				// Prepare and execute DELETE query
				$delete_query = "DELETE FROM course_offering_info WHERE courses_id = ? AND section_id = ?";
				$stmt = $this->db->prepare($delete_query);
				if (!$stmt) {
					echo 'Error preparing delete query: ' . $this->db->error;
					return;
				}
				$stmt->bind_param("ii", $courses_id, $section_id);
				$stmt->execute();


				if ($stmt->affected_rows > 0) {
					echo 'Removed Course Offered!';
				} else {
					echo 'Failed to remove Course Offered!';
				}
			} else {
				echo 'Course Offered not found!';
			}
		} else {
			echo 'Invalid Request!';
		}
	}

	function add_schedule()
	{
		if ($_SERVER['REQUEST_METHOD'] === 'POST') {
			// Retrieving data from the request
			$course_offering_info_id = $_POST['course_offering_info_id'];
			$day = $_POST['day'];
			$time_start = $_POST['time_start'];
			$time_end = $_POST['time_end'];
			$section_id = $_POST['section_id'];
			$room_id = $_POST['room_id'];

			// Query to check if the same schedule exists
			$query = "SELECT * FROM course_offering_info
                  JOIN schedules ON course_offering_info.id = schedules.course_offering_info_id
                  WHERE course_offering_info.section_id = '$section_id'
                  AND schedules.day = '$day'
                  AND schedules.time_start = '" . date('H:i:s', strtotime($time_start)) . "'
                  AND schedules.time_end = '" . date('H:i:s', strtotime($time_end)) . "'";

			// Execute the query
			$result = mysqli_query($this->db, $query);

			// Check if the same schedule exists
			if (mysqli_num_rows($result) == 0) {
				// Insert new schedule
				$new_schedule_query = "INSERT INTO schedules (day, time_start, time_end, room_id, course_offering_info_id)
                                   VALUES ('$day', '" . date('H:i:s', strtotime($time_start)) . "', '" . date('H:i:s', strtotime($time_end)) . "', '$room_id', '$course_offering_info_id')";

				// Execute the insert query
				if (mysqli_query($this->db, $new_schedule_query)) {
					// Respond with success
					header('Content-Type: application/json');
					echo json_encode(array('status' => 'success'));
				} else {
					// Respond with error
					header('Content-Type: application/json');
					echo json_encode(array('status' => 'error', 'message' => mysqli_error($this->db)));
				}
			} else {
				// Respond with error (same schedule found)
				header('Content-Type: application/json');
				echo json_encode(array('status' => 'error', 'message' => 'Same schedule already exists.'));
			}
		}
	}
}
