<?php
include("scripts/settings.php");
include("scripts/settings_dbase_uin.php");
logvalidate($_SESSION['username'], $_SERVER['SCRIPT_FILENAME']);

$response = 1; // 1 for form, 2 for verification display
$student_info = null;
$qualifications = [];
$addresses = [];
$register_user = null;
$new_student = null;

page_header_start();
page_header_end();
page_sidebar();

// Define fields for each table to display and edit
$student_fields = [
    "candidate_name" => "text",
    "father_name" => "text",
    "mother_name" => "text",
    "dob" => "date",
    "aadhar" => "text",
    "gender" => "text",
    "mobile" => "text",
    "email" => "email",
    "religion" => "text",
    "category" => "text",
    "status" => "text",
    "college_roll_no" => "text",
    "photo" => "file",
    "signature" => "file",
    "blood_group" => "text",
    "parent_income" => "text",
    "mother_tongue" => "text",
    "student_id" => "text"
];

$qualification_fields = [
    "name_of_examination" => "text",
    "board_university_name" => "text",
    "college_name" => "text",
    "year" => "text",
    "roll_no" => "text",
    "obtained_marks" => "number",
    "total_marks" => "number",
    "percentage" => "number",
    "division" => "text",
    "cgpa" => "number",
    "status" => "text"
];

$address_fields = [
    "type_of_address" => "text",
    "address" => "text",
    "post" => "text",
    "district" => "text",
    "state" => "text",
    "tehsil" => "text",
    "thana" => "text",
    "pin" => "text"
];

// Check if required tables exist
$required_tables = [
    'admission_student_info',
    'admission_qualification',
    'admission_address',
    'register_users',
    'new_student_info'
];

// Search for student by UIN to load their details for editing
if (isset($_POST['search'])) {
    $uin = isset($_POST['uin']) && is_string($_POST['uin']) ? trim($_POST['uin']) : '';

    // Fetch from register_users
    $reg_sql = "SELECT * FROM register_users WHERE uin_no = ? LIMIT 1";
    $stmt = $conn->prepare($reg_sql);
    $stmt->bind_param("s", $uin);
    $stmt->execute();
    $reg_result = $stmt->get_result();

    if ($reg_result->num_rows > 0) {
        $register_user = $reg_result->fetch_assoc();
        $reg_sno = $register_user['sno'];

        // Fetch from new_student_info
        $new_sql = "SELECT * FROM new_student_info WHERE reg_user_sno = ? LIMIT 1";
        $stmt = $conn->prepare($new_sql);
        $stmt->bind_param("i", $reg_sno);
        $stmt->execute();
        $new_result = $stmt->get_result();
        $new_student = $new_result->num_rows > 0 ? $new_result->fetch_assoc() : null;
        $new_sno = $new_student['sno'];
        $stmt->close();

        // Fetch from admission_student_info
        $student_sql = "SELECT * FROM admission_student_info WHERE student_id = ?";
        $stmt = $conn->prepare($student_sql);
        $stmt->bind_param("i", $new_sno);
        $stmt->execute();
        $student_result = $stmt->get_result();
        if ($student_result->num_rows > 0) {
            $student_info = $student_result->fetch_assoc();
            $student_sno = $student_info['sno'];

            // Fetch qualifications
            $qual_sql = "SELECT * FROM admission_qualification WHERE d_student_info_sno = ? LIMIT 2";
            $stmt = $conn->prepare($qual_sql);
            $stmt->bind_param("i", $student_sno);
            $stmt->execute();
            $qual_result = $stmt->get_result();
            while ($qual_row = $qual_result->fetch_assoc()) {
                $qualifications[] = $qual_row;
            }
            $stmt->close();

            // Ensure at least 2 qualification rows
            while (count($qualifications) < 2) {
                $qualifications[] = array_fill_keys(array_keys($qualification_fields), '');
            }

            // Fetch addresses with case-insensitive comparison
            $addr_sql = "SELECT * FROM admission_address WHERE d_student_info_sno = ? AND UPPER(type_of_address) IN ('PERMANENT', 'CORRESPONDENCE')";
            $stmt = $conn->prepare($addr_sql);
            $stmt->bind_param("i", $student_sno);
            $stmt->execute();
            $addr_result = $stmt->get_result();
            $addresses = [
                'Permanent' => [],
                'Correspondence' => []
            ];
            while ($addr_row = $addr_result->fetch_assoc()) {
                $addr_type = ucfirst(strtolower($addr_row['type_of_address']));
                if ($addr_type !== 'Permanent' && $addr_type !== 'Correspondence') {
                    if (stripos($addr_row['type_of_address'], 'permanent') !== false) {
                        $addr_type = 'Permanent';
                    } elseif (stripos($addr_row['type_of_address'], 'correspondence') !== false) {
                        $addr_type = 'Correspondence';
                    }
                }
                if (in_array($addr_type, ['Permanent', 'Correspondence'])) {
                    $addresses[$addr_type] = $addr_row;
                }
            }
            $stmt->close();

            // Debug: Log the fetched addresses
            // echo "<div class='alert alert-info'>Fetched Addresses: <pre>" . print_r($addresses, true) . "</pre></div>";

            // Initialize default empty values for both address types if not found
            foreach (['Permanent', 'Correspondence'] as $addr_type) {
                if (empty($addresses[$addr_type])) {
                    $addresses[$addr_type] = [
                        'sno' => '',
                        'address' => '',
                        'post' => '',
                        'district' => '',
                        'state' => '',
                        'tehsil' => '',
                        'thana' => '',
                        'pin' => ''
                    ];
                }
            }

            // Check if both address types exist in the database
            $addr_check_sql = "SELECT type_of_address FROM admission_address WHERE d_student_info_sno = ?";
            $stmt = $conn->prepare($addr_check_sql);
            $stmt->bind_param("i", $student_sno);
            $stmt->execute();
            $addr_check_result = $stmt->get_result();
            $address_types_found = [];
            while ($row = $addr_check_result->fetch_assoc()) {
                $addr_type = ucfirst(strtolower($row['type_of_address']));
                if ($addr_type !== 'Permanent' && $addr_type !== 'Correspondence') {
                    if (stripos($row['type_of_address'], 'permanent') !== false) {
                        $addr_type = 'Permanent';
                    } elseif (stripos($row['type_of_address'], 'correspondence') !== false) {
                        $addr_type = 'Correspondence';
                    }
                }
                if (in_array($addr_type, ['Permanent', 'Correspondence'])) {
                    $address_types_found[] = $addr_type;
                }
            }

            if (!in_array('Permanent', $address_types_found) || !in_array('Correspondence', $address_types_found)) {
                echo "<div class='alert alert-danger'>Error: Both Permanent and Correspondence addresses must exist in the database for this student to proceed with editing.</div>";
                $student_info = null; // Prevent form from showing
            }
        } else {
            echo "<div class='alert alert-danger'>No student found in admission_student_info with UIN $uin.</div>";
        }
    } else {
        echo "<div class='alert alert-danger'>No student found with UIN $uin in register_users.</div>";
    }
    $stmt->close();
}

// Update student data across all tables
if (isset($_POST['update_student'])) {
    $uin = isset($_POST['uin']) && is_string($_POST['uin']) ? trim($_POST['uin']) : '';
    $success = true;

    // Fetch the student's SNO values to update the correct records
    $reg_sql = "SELECT sno FROM register_users WHERE uin_no = ? LIMIT 1";
    $stmt = $conn->prepare($reg_sql);
    $stmt->bind_param("s", $uin);
    $stmt->execute();
    $reg_result = $stmt->get_result();
    if ($reg_result->num_rows == 0) {
        $success = false;
        echo "<div class='alert alert-danger'>No student found with UIN $uin in register_users.</div>";
        $stmt->close();
        $response = 1;
    } else {
        $reg_row = $reg_result->fetch_assoc();
        $reg_sno = $reg_row['sno'];
        $stmt->close();

        $new_sql = "SELECT sno FROM new_student_info WHERE reg_user_sno = ? LIMIT 1";
        $stmt = $conn->prepare($new_sql);
        $stmt->bind_param("i", $reg_sno);
        $stmt->execute();
        $new_result = $stmt->get_result();
        $new_row = $new_result->fetch_assoc();
        $new_sno = $new_row['sno'];
        $stmt->close();

        $student_sql = "SELECT sno FROM admission_student_info WHERE student_id = ? LIMIT 1";
        $stmt = $conn->prepare($student_sql);
        $stmt->bind_param("i", $new_sno);
        $stmt->execute();
        $student_result = $stmt->get_result();
        $student_row = $student_result->fetch_assoc();
        $student_sno = $student_row['sno'];
        $stmt->close();

        // Handle file uploads for photo and signature
        $photo_path = $_POST['existing_photo'] ?? '';
        $signature_path = $_POST['existing_signature'] ?? '';
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $max_size = 2 * 1024 * 1024; 

        if (isset($_FILES['photo']) && $_FILES['photo']['error'] == 0) {
            if (!in_array($_FILES['photo']['type'], $allowed_types)) {
                $success = false;
                echo "<div class='alert alert-danger'>Photo must be JPEG, PNG, or GIF.</div>";
            } elseif ($_FILES['photo']['size'] > $max_size) {
                $success = false;
                echo "<div class='alert alert-danger'>Photo size must be less than 2MB.</div>";
            } else {
                $photo_tmp = $_FILES['photo']['tmp_name'];
                $photo_name = basename($_FILES['photo']['name']);
                $photo_path = "uploads/photos/" . time() . "_" . $photo_name;
                if (!move_uploaded_file($photo_tmp, $photo_path)) {
                    $success = false;
                    echo "<div class='alert alert-danger'>Error uploading photo.</div>";
                }
            }
        }

        if (isset($_FILES['signature']) && $_FILES['signature']['error'] == 0) {
            if (!in_array($_FILES['signature']['type'], $allowed_types)) {
                $success = false;
                echo "<div class='alert alert-danger'>Signature must be JPEG, PNG, or GIF.</div>";
            } elseif ($_FILES['signature']['size'] > $max_size) {
                $success = false;
                echo "<div class='alert alert-danger'>Signature size must be less than 2MB.</div>";
            } else {
                $signature_tmp = $_FILES['signature']['tmp_name'];
                $signature_name = basename($_FILES['signature']['name']);
                $signature_path = "uploads/signatures/" . time() . "_" . $signature_name;
                if (!move_uploaded_file($signature_tmp, $signature_path)) {
                    $success = false;
                    echo "<div class='alert alert-danger'>Error uploading signature.</div>";
                }
            }
        }

        if ($success) {
            // Update register_users
            $user_name = isset($_POST['user_name']) && is_string($_POST['user_name']) ? trim($_POST['user_name']) : $uin;
            $candidate_name = isset($_POST['candidate_name']) && is_string($_POST['candidate_name']) ? trim($_POST['candidate_name']) : '';
            $father_name = isset($_POST['father_name']) && is_string($_POST['father_name']) ? trim($_POST['father_name']) : '';
            $mother_name = isset($_POST['mother_name']) && is_string($_POST['mother_name']) ? trim($_POST['mother_name']) : '';
            $dob = isset($_POST['dob']) && is_string($_POST['dob']) ? trim($_POST['dob']) : '';
            $mobile = isset($_POST['mobile']) && is_string($_POST['mobile']) ? trim($_POST['mobile']) : '';
            $email = isset($_POST['email']) && is_string($_POST['email']) ? trim($_POST['email']) : '';
            $courses = isset($_POST['course_applying_for']) && is_string($_POST['course_applying_for']) ? trim($_POST['course_applying_for']) : '';

            $update_reg = "UPDATE register_users 
                SET user_name = ?, full_name = ?, father_name = ?, mother_name = ?, date_of_birth = ?, mobile = ?, e_mail = ?, courses = ? 
                WHERE sno = ?";
            $stmt = $conn->prepare($update_reg);
            $stmt->bind_param("ssssssssi", 
                $user_name, $candidate_name, $father_name, $mother_name, $dob, 
                $mobile, $email, $courses, $reg_sno
            );
            if (!$stmt->execute()) {
                $success = false;
                // echo "<div class='alert alert-danger'>Error updating register_users: " . $stmt->error . "</div>";
            }
            $stmt->close();

            // Update new_student_info
            if ($success) {
                $course_type = isset($_POST['course_type']) && is_string($_POST['course_type']) ? trim($_POST['course_type']) : '';
                $course_applying_for = isset($_POST['course_applying_for']) && is_string($_POST['course_applying_for']) ? trim($_POST['course_applying_for']) : '';
                $category = isset($_POST['category']) && is_string($_POST['category']) ? trim($_POST['category']) : '';

                $update_new = "UPDATE new_student_info 
                    SET candidate_name = ?, father_name = ?, mother_name = ?, dob = ?, mobile = ?, email = ?, course_type = ?, course_applying_for = ?, category = ? 
                    WHERE sno = ?";
                $stmt = $conn->prepare($update_new);
                $stmt->bind_param("sssssssssi", 
                    $candidate_name, $father_name, $mother_name, $dob, $mobile, 
                    $email, $course_type, $course_applying_for, $category, $new_sno
                );
                if (!$stmt->execute()) {
                    $success = false;
                    // echo "<div class='alert alert-danger'>Error updating new_student_info: " . $stmt->error . "</div>";
                }
                $stmt->close();
            }

            // Update admission_student_info
            if ($success) {
                $aadhar = isset($_POST['aadhar']) && is_string($_POST['aadhar']) ? trim($_POST['aadhar']) : '';
                $gender = isset($_POST['gender']) && is_string($_POST['gender']) ? trim($_POST['gender']) : '';
                $religion = isset($_POST['religion']) && is_string($_POST['religion']) ? trim($_POST['religion']) : '';
                $status = isset($_POST['status']) && is_string($_POST['status']) ? trim($_POST['status']) : '';
                $college_roll_no = isset($_POST['college_roll_no']) && is_string($_POST['college_roll_no']) ? trim($_POST['college_roll_no']) : '';
                $blood_group = isset($_POST['blood_group']) && is_string($_POST['blood_group']) ? trim($_POST['blood_group']) : '';
                $parent_income = isset($_POST['parent_income']) && is_string($_POST['parent_income']) ? trim($_POST['parent_income']) : '';
                $mother_tongue = isset($_POST['mother_tongue']) && is_string($_POST['mother_tongue']) ? trim($_POST['mother_tongue']) : '';

                $update_student = "UPDATE admission_student_info 
                    SET candidate_name = ?, father_name = ?, mother_name = ?, dob = ?, aadhar = ?, gender = ?, mobile = ?, email = ?, religion = ?, category = ?, status = ?, college_roll_no = ?, photo = ?, signature = ?, blood_group = ?, parent_income = ?, mother_tongue = ? 
                    WHERE sno = ?";
                $stmt = $conn->prepare($update_student);
                $stmt->bind_param("sssssssssssssssssi", 
                    $candidate_name, $father_name, $mother_name, $dob, $aadhar, $gender, 
                    $mobile, $email, $religion, $category, $status, $college_roll_no, 
                    $photo_path, $signature_path, $blood_group, $parent_income, $mother_tongue, 
                    $student_sno
                );
                if (!$stmt->execute()) {
                    $success = false;
                    // echo "<div class='alert alert-danger'>Error updating admission_student_info: " . $stmt->error . "</div>";
                }
                $stmt->close();
            }

            // Update admission_qualification (2 rows)
            if ($success) {
                // Fetch existing qualification SNOs
                $qual_snos = [];
                $qual_sql = "SELECT sno FROM admission_qualification WHERE d_student_info_sno = ? LIMIT 2";
                $stmt = $conn->prepare($qual_sql);
                $stmt->bind_param("i", $student_sno);
                $stmt->execute();
                $qual_result = $stmt->get_result();
                while ($qual_row = $qual_result->fetch_assoc()) {
                    $qual_snos[] = $qual_row['sno'];
                }
                $stmt->close();

                for ($i = 0; $i < 2; $i++) {
                    $name_of_examination = isset($_POST['name_of_examination'][$i]) && is_string($_POST['name_of_examination'][$i]) ? trim($_POST['name_of_examination'][$i]) : '';
                    $board_university_name = isset($_POST['board_university_name'][$i]) && is_string($_POST['board_university_name'][$i]) ? trim($_POST['board_university_name'][$i]) : '';
                    $college_name = isset($_POST['college_name'][$i]) && is_string($_POST['college_name'][$i]) ? trim($_POST['college_name'][$i]) : '';
                    $year = isset($_POST['year'][$i]) && is_string($_POST['year'][$i]) ? trim($_POST['year'][$i]) : '';
                    $roll_no = isset($_POST['roll_no'][$i]) && is_string($_POST['roll_no'][$i]) ? trim($_POST['roll_no'][$i]) : '';
                    $obtained_marks = isset($_POST['obtained_marks'][$i]) && is_string($_POST['obtained_marks'][$i]) ? trim($_POST['obtained_marks'][$i]) : '';
                    $total_marks = isset($_POST['total_marks'][$i]) && is_string($_POST['total_marks'][$i]) ? trim($_POST['total_marks'][$i]) : '';
                    $percentage = isset($_POST['percentage'][$i]) && is_string($_POST['percentage'][$i]) ? trim($_POST['percentage'][$i]) : '';
                    $division = isset($_POST['division'][$i]) && is_string($_POST['division'][$i]) ? trim($_POST['division'][$i]) : '';
                    $cgpa = isset($_POST['cgpa'][$i]) && is_string($_POST['cgpa'][$i]) ? trim($_POST['cgpa'][$i]) : '';
                    $qual_status = isset($_POST['status'][$i]) && is_string($_POST['status'][$i]) ? trim($_POST['status'][$i]) : '';

                    if (isset($qual_snos[$i])) {
                        // Update existing qualification
                        $update_qual = "UPDATE admission_qualification 
                            SET name_of_examination = ?, board_university_name = ?, college_name = ?, year = ?, roll_no = ?, obtained_marks = ?, total_marks = ?, percentage = ?, division = ?, cgpa = ?, status = ? 
                            WHERE sno = ?";
                        $stmt = $conn->prepare($update_qual);
                        $stmt->bind_param("sssssssssssi", 
                            $name_of_examination, $board_university_name, $college_name, $year, $roll_no, 
                            $obtained_marks, $total_marks, $percentage, $division, $cgpa, $qual_status, 
                            $qual_snos[$i]
                        );
                        if (!$stmt->execute()) {
                            $success = false;
                            echo "<div class='alert alert-danger'>Error updating admission_qualification (Row " . ($i + 1) . "): " . $stmt->error . "</div>";
                        } else {
                            // echo "<div class='alert alert-success'>Admission qualification (Row " . ($i + 1) . ") updated successfully!</div>";
                        }
                        $stmt->close();
                    } else {
                        // Insert new qualification if it doesn't exist
                        $insert_qual = "INSERT INTO admission_qualification 
                            (name_of_examination, board_university_name, college_name, year, roll_no, obtained_marks, total_marks, percentage, division, cgpa, status, d_student_info_sno) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                        $stmt = $conn->prepare($insert_qual);
                        $stmt->bind_param("sssssssssssi", 
                            $name_of_examination, $board_university_name, $college_name, $year, $roll_no, 
                            $obtained_marks, $total_marks, $percentage, $division, $cgpa, $qual_status, 
                            $student_sno
                        );
                        if (!$stmt->execute()) {
                            $success = false;
                            echo "<div class='alert alert-danger'>Error inserting admission_qualification (Row " . ($i + 1) . "): " . $stmt->error . "</div>";
                        }
                        $stmt->close();
                    }
                }
            }

            // Update admission_address (Permanent and Correspondence)
            if ($success) {
                // Check if both address types exist in the database
                $addr_check_sql = "SELECT type_of_address FROM admission_address WHERE d_student_info_sno = ?";
                $stmt = $conn->prepare($addr_check_sql);
                $stmt->bind_param("i", $student_sno);
                $stmt->execute();
                $addr_check_result = $stmt->get_result();
                $address_types_found = [];
                while ($row = $addr_check_result->fetch_assoc()) {
                    $addr_type = ucfirst(strtolower($row['type_of_address']));
                    if ($addr_type !== 'Permanent' && $addr_type !== 'Correspondence') {
                        if (stripos($row['type_of_address'], 'permanent') !== false) {
                            $addr_type = 'Permanent';
                        } elseif (stripos($row['type_of_address'], 'correspondence') !== false) {
                            $addr_type = 'Correspondence';
                        }
                    }
                    if (in_array($addr_type, ['Permanent', 'Correspondence'])) {
                        $address_types_found[] = $addr_type;
                    }
                }
                $stmt->close();

                if (!in_array('Permanent', $address_types_found) || !in_array('Correspondence', $address_types_found)) {
                    $success = false;
                    // echo "<div class='alert alert-danger'>Error: Both Permanent and Correspondence addresses must exist in the database to update. Please ensure both address types are present for this student.</div>";
                } else {
                    foreach (['Permanent', 'Correspondence'] as $addr_type) {
                        $addr_address = isset($_POST[$addr_type]['address']) && is_string($_POST[$addr_type]['address']) ? trim($_POST[$addr_type]['address']) : '';
                        $addr_post = isset($_POST[$addr_type]['post']) && is_string($_POST[$addr_type]['post']) ? trim($_POST[$addr_type]['post']) : '';
                        $addr_district = isset($_POST[$addr_type]['district']) && is_string($_POST[$addr_type]['district']) ? trim($_POST[$addr_type]['district']) : '';
                        $addr_state = isset($_POST[$addr_type]['state']) && is_string($_POST[$addr_type]['state']) ? trim($_POST[$addr_type]['state']) : '';
                        $addr_tehsil = isset($_POST[$addr_type]['tehsil']) && is_string($_POST[$addr_type]['tehsil']) ? trim($_POST[$addr_type]['tehsil']) : '';
                        $addr_thana = isset($_POST[$addr_type]['thana']) && is_string($_POST[$addr_type]['thana']) ? trim($_POST[$addr_type]['thana']) : '';
                        $addr_pin = isset($_POST[$addr_type]['pin']) && is_string($_POST[$addr_type]['pin']) ? trim($_POST[$addr_type]['pin']) : '';

                        $addr_type_normalized = ucfirst(strtolower($addr_type));

                        // Update existing record
                        $addr_sql = "UPDATE admission_address 
                            SET type_of_address = ?, address = ?, post = ?, district = ?, state = ?, tehsil = ?, thana = ?, pin = ? 
                            WHERE d_student_info_sno = ? AND UPPER(type_of_address) = UPPER(?)";
                        $stmt = $conn->prepare($addr_sql);
                        $stmt->bind_param("ssssssssis", 
                            $addr_type_normalized, $addr_address, $addr_post, $addr_district, $addr_state, $addr_tehsil, 
                            $addr_thana, $addr_pin, $student_sno, $addr_type
                        );

                        if (!$stmt->execute()) {
                            $success = false;
                            // echo "<div class='alert alert-danger'>Error updating admission_address ($addr_type): " . $stmt->error . "</div>";
                        } else {
                            // echo "<div class='alert alert-success'>Admission address ($addr_type) updated successfully!</div>";
                        }
                        $stmt->close();
                    }
                }
            }

            if ($success) {
                // echo "<div class='alert alert-success'>Student data updated successfully!</div>";
                $response = 2; // Switch to verification display

                // Re-fetch updated data for verification
                $reg_sql = "SELECT * FROM register_users WHERE uin_no = ? LIMIT 1";
                $stmt = $conn->prepare($reg_sql);
                $stmt->bind_param("s", $uin);
                $stmt->execute();
                $reg_result = $stmt->get_result();
                $register_user = $reg_result->fetch_assoc();
                $reg_sno = $register_user['sno'];
                $stmt->close();

                $new_sql = "SELECT * FROM new_student_info WHERE reg_user_sno = ? LIMIT 1";
                $stmt = $conn->prepare($new_sql);
                $stmt->bind_param("i", $reg_sno);
                $stmt->execute();
                $new_result = $stmt->get_result();
                $new_student = $new_result->num_rows > 0 ? $new_result->fetch_assoc() : null;
                $new_sno = $new_student['sno'];
                $stmt->close();

                $student_sql = "SELECT * FROM admission_student_info WHERE student_id = ?";
                $stmt = $conn->prepare($student_sql);
                $stmt->bind_param("i", $new_sno);
                $stmt->execute();
                $student_result = $stmt->get_result();
                $student_info = $student_result->fetch_assoc();
                $student_sno = $student_info['sno'];
                $stmt->close();

                $qualifications = [];
                $qual_sql = "SELECT * FROM admission_qualification WHERE d_student_info_sno = ? LIMIT 2";
                $stmt = $conn->prepare($qual_sql);
                $stmt->bind_param("i", $student_sno);
                $stmt->execute();
                $qual_result = $stmt->get_result();
                while ($qual_row = $qual_result->fetch_assoc()) {
                    $qualifications[] = $qual_row;
                }
                $stmt->close();

                // Re-fetch updated addresses with case-insensitive comparison
                $addr_sql = "SELECT * FROM admission_address WHERE d_student_info_sno = ? AND UPPER(type_of_address) IN ('PERMANENT', 'CORRESPONDENCE')";
                $stmt = $conn->prepare($addr_sql);
                $stmt->bind_param("i", $student_sno);
                $stmt->execute();
                $addr_result = $stmt->get_result();
                $addresses = [
                    'Permanent' => [],
                    'Correspondence' => []
                ];
                while ($addr_row = $addr_result->fetch_assoc()) {
                    $addr_type = ucfirst(strtolower($addr_row['type_of_address']));
                    if ($addr_type !== 'Permanent' && $addr_type !== 'Correspondence') {
                        if (stripos($addr_row['type_of_address'], 'permanent') !== false) {
                            $addr_type = 'Permanent';
                        } elseif (stripos($addr_row['type_of_address'], 'correspondence') !== false) {
                            $addr_type = 'Correspondence';
                        }
                    }
                    if (in_array($addr_type, ['Permanent', 'Correspondence'])) {
                        $addresses[$addr_type] = $addr_row;
                    }
                }
                $stmt->close();

                // Debug: Log the fetched addresses after update
                // echo "<div class='alert alert-info'>Fetched Addresses After Update: <pre>" . print_r($addresses, true) . "</pre></div>";

                // Initialize default empty values for both address types
                foreach (['Permanent', 'Correspondence'] as $addr_type) {
                    if (empty($addresses[$addr_type])) {
                        $addresses[$addr_type] = [
                            'sno' => '',
                            'address' => '',
                            'post' => '',
                            'district' => '',
                            'state' => '',
                            'tehsil' => '',
                            'thana' => '',
                            'pin' => ''
                        ];
                    }
                }
            }
        }
    }
}
?>
<div class="container">
    <?php if ($response == 1) { ?>
    <div class="row">
        <div class="col-md-12">
            <h2 class="section-header">Search Student by UIN</h2>
            <form action="" method="POST">
                <div class="row">
                    <div class="col-md-4 form-group">
                        <label for="uin" class="form-label">UIN <span class="text-danger">*</span></label>
                        <input type="text" name="uin" id="uin" class="form-control" required>
                    </div>
                    <div class="col-md-12 form-group">
                        <input type="submit" name="search" value="Search" class="btn btn-primary">
                    </div>
                </div>
            </form>
        </div>
    </div>

    <?php if (isset($_POST['search']) && $register_user && $student_info) { ?>
    <form action="" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="uin" value="<?= htmlspecialchars($register_user['uin_no']) ?>">
        <input type="hidden" name="existing_photo" value="<?= htmlspecialchars($student_info['photo'] ?? '') ?>">
        <input type="hidden" name="existing_signature"
            value="<?= htmlspecialchars($student_info['signature'] ?? '') ?>">

        <!-- Admission Student Information -->
        <div class="row">
            <div class="col-md-12">
                <div class="section-header">Admission Student Information</div>
            </div>
            <div class="col-md-4 form-group">
                <label for="uin_no" class="form-label">UIN</label>
                <input type="text" name="uin_no" id="uin_no" class="form-control"
                    value="<?= htmlspecialchars($register_user['uin_no'] ?? '') ?>" readonly>
            </div>
            <div class="col-md-4 form-group">
                <label for="user_name" class="form-label">Username</label>
                <input type="text" name="user_name" id="user_name" class="form-control"
                    value="<?= htmlspecialchars($register_user['user_name'] ?? '') ?>">
            </div>
            <div class="col-md-4 form-group">
                <label for="college_roll_no" class="form-label">College Roll No.</label>
                <input type="text" name="college_roll_no" id="college_roll_no" class="form-control"
                    value="<?= htmlspecialchars($student_info['college_roll_no'] ?? '') ?>">
            </div>
            <div class="col-md-4 form-group">
                <label for="candidate_name" class="form-label">Candidate's Full Name</label>
                <input type="text" name="candidate_name" id="candidate_name" class="form-control"
                    value="<?= htmlspecialchars($student_info['candidate_name'] ?? '') ?>">
            </div>
            <div class="col-md-4 form-group">
                <label for="father_name" class="form-label">Father's Name</label>
                <input type="text" name="father_name" id="father_name" class="form-control"
                    value="<?= htmlspecialchars($student_info['father_name'] ?? '') ?>">
            </div>
            <div class="col-md-4 form-group">
                <label for="mother_name" class="form-label">Mother's Name</label>
                <input type="text" name="mother_name" id="mother_name" class="form-control"
                    value="<?= htmlspecialchars($student_info['mother_name'] ?? '') ?>">
            </div>
            <div class="col-md-4 form-group">
                <label for="dob" class="form-label">Date of Birth</label>
                <input type="date" name="dob" id="dob" class="form-control"
                    value="<?= htmlspecialchars($student_info['dob'] ?? '') ?>">
            </div>
            <div class="col-md-4 form-group">
                <label for="aadhar" class="form-label">Aadhar</label>
                <input type="text" name="aadhar" id="aadhar" class="form-control"
                    value="<?= htmlspecialchars($student_info['aadhar'] ?? '') ?>">
            </div>
            <div class="col-md-4 form-group">
                <label for="gender" class="form-label">Gender</label>
                <select name="gender" id="gender" class="form-control">
                    <option value="Male" <?= ($student_info['gender'] ?? '') == 'Male' ? 'selected' : '' ?>>Male
                    </option>
                    <option value="Female" <?= ($student_info['gender'] ?? '') == 'Female' ? 'selected' : '' ?>>Female
                    </option>
                    <option value="Other" <?= ($student_info['gender'] ?? '') == 'Other' ? 'selected' : '' ?>>Other
                    </option>
                </select>
            </div>
            <div class="col-md-4 form-group">
                <label for="mobile" class="form-label">Mobile</label>
                <input type="text" name="mobile" id="mobile" class="form-control"
                    value="<?= htmlspecialchars($student_info['mobile'] ?? '') ?>">
            </div>
            <div class="col-md-4 form-group">
                <label for="email" class="form-label">Email</label>
                <input type="email" name="email" id="email" class="form-control"
                    value="<?= htmlspecialchars($student_info['email'] ?? '') ?>">
            </div>
            <div class="col-md-4 form-group">
                <label for="religion" class="form-label">Religion</label>
                <select name="religion" id="religion" class="form-control">
                    <option value="">--Select Your Religion--</option>
                    <option value="Hindu" <?= ($student_info['religion'] ?? '') == 'Hindu' ? 'selected' : '' ?>>Hindu
                    </option>
                    <option value="Muslim" <?= ($student_info['religion'] ?? '') == 'Muslim' ? 'selected' : '' ?>>Muslim
                    </option>
                    <option value="Christian" <?= ($student_info['religion'] ?? '') == 'Christian' ? 'selected' : '' ?>>
                        Christian</option>
                    <option value="Sikh" <?= ($student_info['religion'] ?? '') == 'Sikh' ? 'selected' : '' ?>>Sikh
                    </option>
                    <option value="Other" <?= ($student_info['religion'] ?? '') == 'Other' ? 'selected' : '' ?>>Other
                    </option>
                </select>
            </div>
            <div class="col-md-4 form-group">
                <label for="category" class="form-label">Category</label>
                <select name="category" id="category" class="form-control">
                    <option value="GEN" <?= ($student_info['category'] ?? '') == 'GEN' ? 'selected' : '' ?>>GEN</option>
                    <option value="OBC" <?= ($student_info['category'] ?? '') == 'OBC' ? 'selected' : '' ?>>OBC</option>
                    <option value="SC" <?= ($student_info['category'] ?? '') == 'SC' ? 'selected' : '' ?>>SC</option>
                    <option value="ST" <?= ($student_info['category'] ?? '') == 'ST' ? 'selected' : '' ?>>ST</option>
                </select>
            </div>
            <div class="col-md-4 form-group">
                <label for="status" class="form-label">Status</label>
                <select name="status" id="status" class="form-control">
                    <option value="Active" <?= ($student_info['status'] ?? '') == 'Active' ? 'selected' : '' ?>>Active
                    </option>
                    <option value="Inactive" <?= ($student_info['status'] ?? '') == 'Inactive' ? 'selected' : '' ?>>
                        Inactive</option>
                </select>
            </div>
            <div class="col-md-4 form-group">
                <label for="photo" class="form-label">Photo</label>
                <?php if (!empty($student_info['photo'])) { ?>
                <div class="upload-preview">
                    <img src="<?= htmlspecialchars($student_info['photo']) ?>" alt="Photo">
                </div>
                <?php } ?>
                <input type="file" name="photo" id="photo" class="form-control" accept="image/*">
            </div>
            <div class="col-md-4 form-group">
                <label for="signature" class="form-label">Signature</label>
                <?php if (!empty($student_info['signature'])) { ?>
                <div class="upload-preview">
                    <img src="<?= htmlspecialchars($student_info['signature']) ?>" alt="Signature">
                </div>
                <?php } ?>
                <input type="file" name="signature" id="signature" class="form-control" accept="image/*">
            </div>
            <div class="col-md-4 form-group">
                <label for="blood_group" class="form-label">Blood Group</label>
                <input type="text" name="blood_group" id="blood_group" class="form-control"
                    value="<?= htmlspecialchars($student_info['blood_group'] ?? '') ?>">
            </div>
            <div class="col-md-4 form-group">
                <label for="parent_income" class="form-label">Parent Mobile No.</label>
                <input type="text" name="parent_income" id="parent_income" class="form-control"
                    value="<?= htmlspecialchars($student_info['parent_income'] ?? '') ?>">
            </div>
            <div class="col-md-4 form-group">
                <label for="mother_tongue" class="form-label">Mother Tongue</label>
                <input type="text" name="mother_tongue" id="mother_tongue" class="form-control"
                    value="<?= htmlspecialchars($student_info['mother_tongue'] ?? '') ?>">
            </div>
            <div class="col-md-4 form-group">
                <label for="course_type" class="form-label">Course Type</label>
                <input type="text" name="course_type" id="course_type" class="form-control"
                    value="<?= htmlspecialchars($new_student['course_type'] ?? '') ?>">
            </div>
            <div class="col-md-4 form-group">
                <label for="course_applying_for" class="form-label">Course Applying For</label>
                <input type="text" name="course_applying_for" id="course_applying_for" class="form-control"
                    value="<?= htmlspecialchars($new_student['course_applying_for'] ?? '') ?>">
            </div>
        </div>

        <!-- Permanent Address -->
        <div class="row">
            <div class="col-md-12">
                <div class="section-header">Admission Address Information (Permanent Address)</div>
            </div>
            <div class="col-md-4 form-group">
                <label for="Permanent_address" class="form-label">Address/Village</label>
                <input type="text" name="Permanent[address]" id="Permanent_address" class="form-control"
                    value="<?= htmlspecialchars($addresses['Permanent']['address'] ?? '') ?>">
            </div>
            <div class="col-md-4 form-group">
                <label for="Permanent_post" class="form-label">Post</label>
                <input type="text" name="Permanent[post]" id="Permanent_post" class="form-control"
                    value="<?= htmlspecialchars($addresses['Permanent']['post'] ?? '') ?>">
            </div>
            <div class="col-md-4 form-group">
                <label for="Permanent_thana" class="form-label">Thana</label>
                <input type="text" name="Permanent[thana]" id="Permanent_thana" class="form-control"
                    value="<?= htmlspecialchars($addresses['Permanent']['thana'] ?? '') ?>">
            </div>
            <div class="col-md-4 form-group">
                <label for="Permanent_tehsil" class="form-label">Tehsil</label>
                <input type="text" name="Permanent[tehsil]" id="Permanent_tehsil" class="form-control"
                    value="<?= htmlspecialchars($addresses['Permanent']['tehsil'] ?? '') ?>">
            </div>
            <div class="col-md-4 form-group">
                <label for="Permanent_district" class="form-label">District</label>
                <input type="text" name="Permanent[district]" id="Permanent_district" class="form-control"
                    value="<?= htmlspecialchars($addresses['Permanent']['district'] ?? '') ?>">
            </div>
            <div class="col-md-4 form-group">
                <label for="Permanent_state" class="form-label">State</label>
                <input type="text" name="Permanent[state]" id="Permanent_state" class="form-control"
                    value="<?= htmlspecialchars($addresses['Permanent']['state'] ?? '') ?>">
            </div>
            <div class="col-md-4 form-group">
                <label for="Permanent_pin" class="form-label">Pin</label>
                <input type="text" name="Permanent[pin]" id="Permanent_pin" class="form-control"
                    value="<?= htmlspecialchars($addresses['Permanent']['pin'] ?? '') ?>">
            </div>
            <input type="hidden" name="Permanent[type_of_address]" value="Permanent">
        </div>

        <!-- Correspondence Address -->
        <div class="row">
            <div class="col-md-12">
                <div class="section-header">Admission Address Information (Correspondence Address)</div>
                <div class="form-group">
                    <input type="checkbox" id="same_as_permanent" name="same_as_permanent">
                    <label for="same_as_permanent">Same as Permanent Address</label>
                </div>
            </div>
            <div class="col-md-4 form-group">
                <label for="Correspondence_address" class="form-label">Address/Village</label>
                <input type="text" name="Correspondence[address]" id="Correspondence_address" class="form-control"
                    value="<?= htmlspecialchars($addresses['Correspondence']['address'] ?? '') ?>">
            </div>
            <div class="col-md-4 form-group">
                <label for="Correspondence_post" class="form-label">Post</label>
                <input type="text" name="Correspondence[post]" id="Correspondence_post" class="form-control"
                    value="<?= htmlspecialchars($addresses['Correspondence']['post'] ?? '') ?>">
            </div>
            <div class="col-md-4 form-group">
                <label for="Correspondence_thana" class="form-label">Thana</label>
                <input type="text" name="Correspondence[thana]" id="Correspondence_thana" class="form-control"
                    value="<?= htmlspecialchars($addresses['Correspondence']['thana'] ?? '') ?>">
            </div>
            <div class="col-md-4 form-group">
                <label for="Correspondence_tehsil" class="form-label">Tehsil</label>
                <input type="text" name="Correspondence[tehsil]" id="Correspondence_tehsil" class="form-control"
                    value="<?= htmlspecialchars($addresses['Correspondence']['tehsil'] ?? '') ?>">
            </div>
            <div class="col-md-4 form-group">
                <label for="Correspondence_district" class="form-label">District</label>
                <input type="text" name="Correspondence[district]" id="Correspondence_district" class="form-control"
                    value="<?= htmlspecialchars($addresses['Correspondence']['district'] ?? '') ?>">
            </div>
            <div class="col-md-4 form-group">
                <label for="Correspondence_state" class="form-label">State</label>
                <input type="text" name="Correspondence[state]" id="Correspondence_state" class="form-control"
                    value="<?= htmlspecialchars($addresses['Correspondence']['state'] ?? '') ?>">
            </div>
            <div class="col-md-4 form-group">
                <label for="Correspondence_pin" class="form-label">Pin</label>
                <input type="text" name="Correspondence[pin]" id="Correspondence_pin" class="form-control"
                    value="<?= htmlspecialchars($addresses['Correspondence']['pin'] ?? '') ?>">
            </div>
            <input type="hidden" name="Correspondence[type_of_address]" value="Correspondence">
        </div>

        <!-- Admission Qualification Information -->
        <div class="row">
            <div class="col-md-12">
                <div class="section-header">Admission Qualification Information</div>
                <table>
                    <thead>
                        <tr>
                            <th>S.No</th>
                            <th>Name of Examination</th>
                            <th>Board/University Name</th>
                            <th>College Name</th>
                            <th>Year</th>
                            <th>Roll No</th>
                            <th>Obtained Marks</th>
                            <th>Total Marks</th>
                            <th>Percentage</th>
                            <th>Division</th>
                            <th>CGPA</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php for ($i = 0; $i < 2; $i++) { ?>
                        <tr>
                            <td><?= $i + 1 ?></td>
                            <td><input type="text" name="name_of_examination[]" class="form-control"
                                    value="<?= htmlspecialchars($qualifications[$i]['name_of_examination'] ?? '') ?>">
                            </td>
                            <td><input type="text" name="board_university_name[]" class="form-control"
                                    value="<?= htmlspecialchars($qualifications[$i]['board_university_name'] ?? '') ?>">
                            </td>
                            <td><input type="text" name="college_name[]" class="form-control"
                                    value="<?= htmlspecialchars($qualifications[$i]['college_name'] ?? '') ?>"></td>
                            <td><input type="text" name="year[]" class="form-control"
                                    value="<?= htmlspecialchars($qualifications[$i]['year'] ?? '') ?>"></td>
                            <td><input type="text" name="roll_no[]" class="form-control"
                                    value="<?= htmlspecialchars($qualifications[$i]['roll_no'] ?? '') ?>"></td>
                            <td><input type="number" name="obtained_marks[]" class="form-control obtained_marks"
                                    data-row="<?= $i ?>"
                                    value="<?= htmlspecialchars($qualifications[$i]['obtained_marks'] ?? '') ?>"></td>
                            <td><input type="number" name="total_marks[]" class="form-control total_marks"
                                    data-row="<?= $i ?>"
                                    value="<?= htmlspecialchars($qualifications[$i]['total_marks'] ?? '') ?>"></td>
                            <td><input type="number" step="any" name="percentage[]" class="form-control percentage"
                                    data-row="<?= $i ?>"
                                    value="<?= htmlspecialchars($qualifications[$i]['percentage'] ?? '') ?>"></td>
                            <td><input type="text" name="division[]" class="form-control"
                                    value="<?= htmlspecialchars($qualifications[$i]['division'] ?? '') ?>"></td>
                            <td><input type="number" step="any" name="cgpa[]" class="form-control"
                                    value="<?= htmlspecialchars($qualifications[$i]['cgpa'] ?? '') ?>"></td>
                            <td>
                                <select name="status[]" class="form-control">
                                    <option value="">--Select--</option>
                                    <option value="Passed"
                                        <?= ($qualifications[$i]['status'] ?? '') == 'Passed' ? 'selected' : '' ?>>
                                        Passed</option>
                                    <option value="Failed"
                                        <?= ($qualifications[$i]['status'] ?? '') == 'Failed' ? 'selected' : '' ?>>
                                        Failed</option>
                                </select>
                            </td>
                        </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="row">
            <div class="col-md-12 form-group">
                <input type="submit" name="update_student" value="UPDATE" class="btn btn-primary">
            </div>
        </div>
    </form>

    <script>
    document.getElementById('same_as_permanent').addEventListener('change', function() {
        if (this.checked) {
            document.getElementById('Correspondence_address').value = document.getElementById(
                'Permanent_address').value;
            document.getElementById('Correspondence_post').value = document.getElementById('Permanent_post')
                .value;
            document.getElementById('Correspondence_thana').value = document.getElementById('Permanent_thana')
                .value;
            document.getElementById('Correspondence_tehsil').value = document.getElementById('Permanent_tehsil')
                .value;
            document.getElementById('Correspondence_district').value = document.getElementById(
                'Permanent_district').value;
            document.getElementById('Correspondence_state').value = document.getElementById('Permanent_state')
                .value;
            document.getElementById('Correspondence_pin').value = document.getElementById('Permanent_pin')
                .value;

            document.getElementById('Correspondence_address').disabled = true;
            document.getElementById('Correspondence_post').disabled = true;
            document.getElementById('Correspondence_thana').disabled = true;
            document.getElementById('Correspondence_tehsil').disabled = true;
            document.getElementById('Correspondence_district').disabled = true;
            document.getElementById('Correspondence_state').disabled = true;
            document.getElementById('Correspondence_pin').disabled = true;
        } else {
            document.getElementById('Correspondence_address').disabled = false;
            document.getElementById('Correspondence_post').disabled = false;
            document.getElementById('Correspondence_thana').disabled = false;
            document.getElementById('Correspondence_tehsil').disabled = false;
            document.getElementById('Correspondence_district').disabled = false;
            document.getElementById('Correspondence_state').disabled = false;
            document.getElementById('Correspondence_pin').disabled = false;
        }
    });

    document.querySelectorAll('.obtained_marks, .total_marks').forEach(input => {
        input.addEventListener('input', function() {
            const row = this.dataset.row;
            const obtained = parseFloat(document.querySelector(`.obtained_marks[data-row="${row}"]`)
                .value) || 0;
            const total = parseFloat(document.querySelector(`.total_marks[data-row="${row}"]`).value) ||
                0;
            const percentageField = document.querySelector(`.percentage[data-row="${row}"]`);
            if (total > 0) {
                const percentage = (obtained / total) * 100;
                percentageField.value = percentage.toFixed(2);
            } else {
                percentageField.value = '';
            }
        });
    });
    </script>
    <?php } ?>
    <?php } elseif ($response == 2) { ?>
    <div class="row">
        <div class="col-md-12">
            <div class="section-header">Updated Student Information</div>
            <table>
                <tr>
                    <th colspan="2">Admission Student Information</th>
                </tr>
                <tr>
                    <th>UIN:</th>
                    <td><?= htmlspecialchars($register_user['uin_no'] ?? '') ?></td>
                </tr>
                <tr>
                    <th>Username:</th>
                    <td><?= htmlspecialchars($register_user['user_name'] ?? '') ?></td>
                </tr>
                <?php
                foreach ($student_fields as $field => $type) {
                    $label = ucwords(str_replace('_', ' ', $field));
                    if ($field == 'photo' || $field == 'signature') {
                        $value = !empty($student_info[$field]) ? "<img src='" . htmlspecialchars($student_info[$field]) . "' style='max-width:100px; max-height:100px;'>" : '';
                    } else {
                        $value = htmlspecialchars($student_info[$field] ?? '');
                    }
                    echo "<tr><th>$label:</th><td>$value</td></tr>";
                }
                ?>
                <tr>
                    <th colspan="2">Permanent Address</th>
                </tr>
                <?php
                foreach ($address_fields as $field => $type) {
                    if ($field == 'type_of_address') continue;
                    $label = ucwords(str_replace('_', ' ', $field));
                    $value = isset($addresses['Permanent'][$field]) ? htmlspecialchars($addresses['Permanent'][$field]) : 'Not Set';
                    echo "<tr><th>$label:</th><td>$value</td></tr>";
                }
                ?>
                <tr>
                    <th colspan="2">Correspondence Address</th>
                </tr>
                <?php
                foreach ($address_fields as $field => $type) {
                    if ($field == 'type_of_address') continue;
                    $label = ucwords(str_replace('_', ' ', $field));
                    $value = isset($addresses['Correspondence'][$field]) ? htmlspecialchars($addresses['Correspondence'][$field]) : 'Not Set';
                    echo "<tr><th>$label:</th><td>$value</td></tr>";
                }
                ?>
                <tr>
                    <th colspan="2">Admission Qualification Information</th>
                </tr>
                <?php
                foreach ($qualifications as $index => $qualification) {
                    echo "<tr><th colspan='2'>Qualification " . ($index + 1) . "</th></tr>";
                    foreach ($qualification_fields as $field => $type) {
                        $label = ucwords(str_replace('_', ' ', $field));
                        echo "<tr><th>$label:</th><td>" . htmlspecialchars($qualification[$field] ?? '') . "</td></tr>";
                    }
                }
                ?>
                <tr>
                    <td colspan='2'><a href='' class='btn btn-primary'>Back to Search</a></td>
                </tr>
            </table>
        </div>
    </div>
    <?php } ?>
</div>

<?php
page_footer_start();
page_footer_end();
?>