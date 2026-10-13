<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}
include '../config/db.php';

$success = $error = '';
$preview_data = [];
$step = 'upload';

// ===== FACULTIES & DEPARTMENTS MAPS =====
$faculties = [];
$res = $conn->query("SELECT id, name FROM faculties ORDER BY name");
if ($res) $faculties = $res->fetch_all(MYSQLI_ASSOC);

$departments = [];
$res = $conn->query("SELECT id, name, faculty_id FROM departments ORDER BY name");
if ($res) $departments = $res->fetch_all(MYSQLI_ASSOC);

$faculty_map = [];
foreach ($faculties as $f) $faculty_map[strtolower(trim($f['name']))] = $f['id'];

$dept_map = [];
foreach ($departments as $d) {
    $key = strtolower(trim($d['name']));
    if (!isset($dept_map[$key])) $dept_map[$key] = $d['id'];
}

// ===== CSV PARSING =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['parse_csv'])) {
    $step = 'preview';
    $csv_data = [];
    $headers = null;

    if (!empty($_FILES['csv_file']['tmp_name'])) {
        $file = $_FILES['csv_file'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, ['csv', 'txt'])) {
            $error = "Only CSV files are allowed. Got: .$ext";
            $step = 'upload';
        } elseif ($file['size'] > 5 * 1024 * 1024) {
            $error = "File must be under 5MB.";
            $step = 'upload';
        } else {
            if (($handle = fopen($file['tmp_name'], 'r')) !== false) {
                $row_num = 0;
                while (($row = fgetcsv($handle, 10000, ',')) !== false) {
                    $row_num++;
                    if ($row_num === 1) {
                        $headers = array_map(function($h) {
                            return strtolower(trim(str_replace([' ', '-'], '_', $h)));
                        }, $row);
                        continue;
                    }
                    if (count(array_filter($row)) === 0) continue;
                    $csv_data[] = ['row' => $row_num, 'data' => $row];
                }
                fclose($handle);
            } else {
                $error = "Failed to read the uploaded file.";
                $step = 'upload';
            }
        }
    } elseif (!empty($_POST['pasted_data'])) {
        $lines = preg_split('/\r\n|\r|\n/', trim($_POST['pasted_data']));
        $row_num = 0;
        foreach ($lines as $line) {
            $row_num++;
            if (empty(trim($line))) continue;
            $delimiter = (strpos($line, "\t") !== false) ? "\t" : ",";
            $row = str_getcsv($line, $delimiter);
            if ($row_num === 1) {
                $headers = array_map(function($h) {
                    return strtolower(trim(str_replace([' ', '-'], '_', $h)));
                }, $row);
                continue;
            }
            $csv_data[] = ['row' => $row_num, 'data' => $row];
        }
    } else {
        $error = "Please upload a CSV file or paste your data.";
        $step = 'upload';
    }

    // ===== PROCESS ROWS =====
    if ($step === 'preview' && !empty($csv_data) && !empty($headers)) {
        $required = ['matric_no', 'full_name', 'surname', 'email', 'phone', 'gender', 'faculty', 'department', 'level', 'semester'];
        $missing = array_diff($required, $headers);

        if (!empty($missing)) {
            $error = "Missing required columns: " . implode(', ', $missing) . ". Please check your CSV header row.";
            $step = 'upload';
        } else {
            $seen_matrics = [];
            $seen_emails = [];

            foreach ($csv_data as $entry) {
                $row = $entry['data'];
                $row_data = [];
                foreach ($headers as $i => $h) {
                    $row_data[$h] = isset($row[$i]) ? trim($row[$i]) : '';
                }

                $row_errors = [];

                $matric = strtoupper($row_data['matric_no'] ?? '');
                $full_name = strtoupper($row_data['full_name'] ?? '');
                $surname = strtoupper($row_data['surname'] ?? '');
                $email = strtolower($row_data['email'] ?? '');
                $phone = $row_data['phone'] ?? '';
                $gender = ucfirst(strtolower($row_data['gender'] ?? ''));
                $faculty_name = $row_data['faculty'] ?? '';
                $dept_name = $row_data['department'] ?? '';
                $level = strtoupper(trim($row_data['level'] ?? ''));
                $semester = $row_data['semester'] ?? '1';

                // Auto-derive surname if missing
                if (empty($surname) && !empty($full_name)) {
                    $parts = preg_split('/\s+/', $full_name);
                    $surname = $parts[0];
                }

                // Validation
                if (empty($matric)) $row_errors[] = "Matric no missing";
                if (empty($full_name)) $row_errors[] = "Full name missing";
                if (empty($surname)) $row_errors[] = "Surname missing";
                if (empty($email)) $row_errors[] = "Email missing";
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $row_errors[] = "Invalid email format";

                // Gender normalization
                if (in_array(strtolower($gender), ['m', 'male'])) $gender = 'Male';
                elseif (in_array(strtolower($gender), ['f', 'female'])) $gender = 'Female';
                else $row_errors[] = "Invalid gender (Male/Female)";

                // Level normalization
                $level_clean = strtoupper(str_replace(' ', '', $level));
                $level_map = [
                    'ND1' => 'ND1', 'ND2' => 'ND2', 'HND1' => 'HND1', 'HND2' => 'HND2',
                    '100' => 'ND1', '200' => 'ND2', '300' => 'HND1', '400' => 'HND2',
                ];
                if (isset($level_map[$level_clean])) {
                    $level = $level_map[$level_clean];
                } else {
                    $row_errors[] = "Invalid level (ND1/ND2/HND1/HND2)";
                }

                // Semester
                $sem_clean = strtolower(trim($semester));
                if (in_array($sem_clean, ['1', 'first'])) $semester = '1';
                elseif (in_array($sem_clean, ['2', 'second'])) $semester = '2';
                else $row_errors[] = "Invalid semester (1 or 2)";

                // Faculty lookup
                $faculty_id = 0;
                if (!empty($faculty_name)) {
                    $key = strtolower(trim($faculty_name));
                    if (isset($faculty_map[$key])) {
                        $faculty_id = $faculty_map[$key];
                    } else {
                        foreach ($faculty_map as $n => $id) {
                            if (strpos($n, $key) !== false || strpos($key, $n) !== false) {
                                $faculty_id = $id; break;
                            }
                        }
                        if (!$faculty_id) $row_errors[] = "Faculty not found: '$faculty_name'";
                    }
                } else {
                    $row_errors[] = "Faculty missing";
                }

                // Department lookup
                $department_id = 0;
                if (!empty($dept_name)) {
                    $key = strtolower(trim($dept_name));
                    if (isset($dept_map[$key])) {
                        $department_id = $dept_map[$key];
                    } else {
                        foreach ($dept_map as $n => $id) {
                            if (strpos($n, $key) !== false || strpos($key, $n) !== false) {
                                $department_id = $id; break;
                            }
                        }
                        if (!$department_id) $row_errors[] = "Department not found: '$dept_name'";
                    }
                } else {
                    $row_errors[] = "Department missing";
                }

                // DB duplicate check
                $is_duplicate = false;
                if (!empty($matric)) {
                    $chk = $conn->prepare("SELECT id FROM students WHERE matric_no = ? OR email = ?");
                    $chk->bind_param("ss", $matric, $email);
                    $chk->execute();
                    if ($chk->get_result()->num_rows > 0) {
                        $is_duplicate = true;
                        $row_errors[] = "Already exists in database";
                    }
                }

                // CSV internal duplicates
                if (!empty($matric)) {
                    if (in_array($matric, $seen_matrics)) $row_errors[] = "Duplicate matric in CSV";
                    else $seen_matrics[] = $matric;
                }
                if (!empty($email)) {
                    if (in_array($email, $seen_emails)) $row_errors[] = "Duplicate email in CSV";
                    else $seen_emails[] = $email;
                }

                $preview_data[] = [
                    'row' => $entry['row'],
                    'matric_no' => $matric,
                    'full_name' => $full_name,
                    'surname' => $surname,
                    'email' => $email,
                    'phone' => $phone,
                    'gender' => $gender,
                    'faculty_id' => $faculty_id,
                    'faculty_name' => $faculty_name,
                    'department_id' => $department_id,
                    'department_name' => $dept_name,
                    'level' => $level,
                    'semester' => $semester,
                    'errors' => $row_errors,
                    'is_duplicate' => $is_duplicate,
                ];
            }
        }
    } elseif ($step === 'preview' && empty($csv_data)) {
        $error = "No data rows found in the file.";
        $step = 'upload';
    }
}

// ===== CONFIRM IMPORT =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_import'])) {
    $rows_json = $_POST['rows_data'] ?? '[]';
    $rows = json_decode($rows_json, true);

    if (empty($rows) || !is_array($rows)) {
        $error = "No data to import.";
        $step = 'upload';
    } else {
        $imported = 0;
        $skipped = 0;

        $conn->begin_transaction();

        try {
            foreach ($rows as $row) {
                if (!empty($row['errors']) || !empty($row['is_duplicate'])) {
                    $skipped++; continue;
                }

                // Re-check duplicate
                $chk = $conn->prepare("SELECT id FROM students WHERE matric_no = ? OR email = ?");
                $chk->bind_param("ss", $row['matric_no'], $row['email']);
                $chk->execute();
                if ($chk->get_result()->num_rows > 0) { $skipped++; continue; }

                // Ensure surname is set
                $surname = $row['surname'] ?? '';
                if (empty($surname) && !empty($row['full_name'])) {
                    $surname = explode(' ', $row['full_name'])[0];
                }

                // Insert
                $stmt = $conn->prepare("
                    INSERT INTO students 
                    (matric_no, full_name, surname, email, phone, gender, faculty_id, department_id, level, semester, password, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, 'active')
                ");
                $stmt->bind_param(
                    "ssssssiiss",
                    $row['matric_no'],
                    $row['full_name'],
                    $surname,
                    $row['email'],
                    $row['phone'],
                    $row['gender'],
                    $row['faculty_id'],
                    $row['department_id'],
                    $row['level'],
                    $row['semester']
                );

                if ($stmt->execute()) $imported++;
                else $skipped++;
            }

            $conn->commit();

            // Log
            if (file_exists('log_action.php')) {
                include_once 'log_action.php';
                if (function_exists('log_action')) {
                    log_action($conn, 'Bulk Import', 'student', null, "Imported $imported students (skipped $skipped)");
                }
            }

            $success = "✅ Successfully imported <strong>$imported</strong> student(s)." . 
                       ($skipped > 0 ? " <strong>$skipped</strong> row(s) skipped due to errors/duplicates." : "");
            $step = 'done';
            $preview_data = [];
        } catch (Exception $e) {
            $conn->rollback();
            $error = "Import failed: " . $e->getMessage();
            $step = 'upload';
        }
    }
}

// ===== DOWNLOAD TEMPLATE =====
if (isset($_GET['download_template'])) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="student_import_template.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['matric_no', 'full_name', 'surname', 'email', 'phone', 'gender', 'faculty', 'department', 'level', 'semester']);
    fputcsv($out, ['2024239010098', 'OKUEWU ODUNAYO GLORIA', 'OKUEWU', 'gloria@example.com', '08012345678', 'Female', 'Science', 'Computer Science', 'HND2', '1']);
    fputcsv($out, ['2024239010099', 'ADEYEMI JOHN OLUWASEUN', 'ADEYEMI', 'john@example.com', '08087654321', 'Male', 'Engineering', 'Mechanical Engineering', 'ND1', '1']);
    fclose($out);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Bulk Student Upload - Dormitory MS</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family: 'Inter', sans-serif; background: #f4f6f9; overflow-x: hidden; }

        .sidebar-toggle-btn { position: fixed; top: 12px; left: 12px; z-index: 1050; background: #1a3a6b; color: white; border: none; border-radius: 10px; padding: 12px 16px; display: none; }
        .sidebar-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1039; }
        .sidebar-overlay.active { display: block; }
        .main-sidebar { position: fixed; top: 0; left: 0; height: 100vh; width: 280px; background: linear-gradient(180deg, #1a3a6b 0%, #0d1b3e 100%); z-index: 1040; overflow-y: auto; transition: transform 0.3s ease; transform: translateX(-100%); }
        .main-sidebar.open { transform: translateX(0); }
        .sidebar-brand { padding: 20px 18px 15px; border-bottom: 1px solid rgba(255,255,255,0.08); display: flex; align-items: center; gap: 12px; }
        .sidebar-brand img { width: 45px; height: 45px; background: white; padding: 5px; border-radius: 10px; }
        .sidebar-brand .brand-text { color: white; font-weight: 700; font-size: 1.1rem; }
        .sidebar-brand .brand-text small { display: block; font-size: 0.7rem; opacity: 0.7; font-weight: 300; }
        .sidebar-close-btn { display: none; position: absolute; top: 12px; right: 12px; background: rgba(255,255,255,0.1); color: white; border: none; border-radius: 50%; width: 36px; height: 36px; }
        .user-panel { padding: 18px 20px !important; border-bottom: 1px solid rgba(255,255,255,0.08); display: flex !important; align-items: center; gap: 12px; }
        .user-panel img { width: 42px; height: 42px; border-radius: 50%; }
        .user-panel a { color: white; font-weight: 600; text-decoration: none; font-size: 0.95rem; }
        .user-panel small { color: rgba(255,255,255,0.6); display: block; font-size: 0.75rem; }
        .nav-sidebar { padding: 8px 0; list-style: none; }
        .nav-sidebar .nav-item { margin: 2px 8px; }
        .nav-sidebar .nav-link { color: rgba(255,255,255,0.75); padding: 10px 16px; border-radius: 10px; display: flex; align-items: center; gap: 12px; text-decoration: none; transition: all 0.3s ease; }
        .nav-sidebar .nav-link:hover { color: white; background: rgba(255,255,255,0.1); }
        .nav-sidebar .nav-link.active { color: white; background: rgba(255,215,0,0.15); border-left: 3px solid #ffd700; }
        .nav-sidebar .nav-icon { width: 22px; text-align: center; color: rgba(255,255,255,0.6); }
        .nav-sidebar .nav-link.active .nav-icon { color: #ffd700; }
        .nav-sidebar .nav-link.text-danger { color: #ff6b6b !important; }
        .nav-treeview { padding-left: 15px; max-height: 0; overflow: hidden; transition: max-height 0.3s ease; }
        .nav-treeview.show { max-height: 500px; }
        .nav-treeview .nav-link { padding: 8px 16px 8px 35px; font-size: 0.85rem; color: rgba(255,255,255,0.6); }

        .content-wrapper { min-height: 100vh; padding: 20px; transition: margin-left 0.3s ease; }
        .content-header { padding-bottom: 15px; border-bottom: 2px solid #e9ecef; margin-bottom: 20px; }
        .content-header h1 { font-weight: 700; font-size: 1.8rem; color: #1a2a4a; }

        .card { border: none; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.06); margin-bottom: 20px; animation: fadeInUp 0.6s ease; }
        @keyframes fadeInUp { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }
        .card-header { background: white; border-bottom: 2px solid #f0f2f5; padding: 18px 25px; border-radius: 16px 16px 0 0 !important; }
        .card-header h3 { font-weight: 700; font-size: 1.15rem; color: #1a2a4a; margin: 0; }
        .card-body { padding: 30px; }
        .card-primary .card-header { background: linear-gradient(135deg, #1a3a6b, #0d1b3e); color: white; }
        .card-primary .card-header h3 { color: white; }

        /* Step indicator */
        .step-indicator { display: flex; justify-content: center; margin-bottom: 30px; }
        .step { display: flex; align-items: center; gap: 10px; color: #adb5bd; font-weight: 600; font-size: 0.9rem; }
        .step .step-num { width: 40px; height: 40px; border-radius: 50%; background: #e9ecef; color: #6c757d; display: flex; align-items: center; justify-content: center; font-weight: 800; transition: all 0.3s ease; }
        .step.active .step-num { background: linear-gradient(135deg, #1a3a6b, #0d1b3e); color: white; box-shadow: 0 4px 15px rgba(26,58,107,0.4); transform: scale(1.1); }
        .step.active { color: #1a3a6b; }
        .step.done .step-num { background: #28a745; color: white; }
        .step.done { color: #28a745; }
        .step-line { width: 80px; height: 2px; background: #e9ecef; margin: 0 10px; align-self: center; }
        .step-line.done { background: #28a745; }
        @media (max-width: 576px) { .step-line { width: 30px; margin: 0 5px; } .step span:not(.step-num) { display: none; } }

        /* Upload zone */
        .upload-zone { border: 3px dashed #cbd5e1; border-radius: 16px; padding: 50px 30px; text-align: center; transition: all 0.3s ease; background: #fafbfc; cursor: pointer; }
        .upload-zone:hover, .upload-zone.dragover { border-color: #1a3a6b; background: #f0f4fa; }
        .upload-zone .upload-icon { font-size: 4rem; color: #1a3a6b; margin-bottom: 15px; }
        .upload-zone h4 { font-weight: 700; color: #1a2a4a; }
        .upload-zone p { color: #6c757d; margin: 8px 0 0; }
        .upload-zone input[type="file"] { display: none; }

        .divider-text { text-align: center; margin: 25px 0; position: relative; font-size: 0.85rem; color: #adb5bd; text-transform: uppercase; letter-spacing: 1px; font-weight: 600; }
        .divider-text::before, .divider-text::after { content: ''; position: absolute; top: 50%; width: 42%; height: 1px; background: #e9ecef; }
        .divider-text::before { left: 0; }
        .divider-text::after { right: 0; }

        .form-control, .form-select { border: 2px solid #e9ecef; border-radius: 10px; padding: 11px 16px; font-size: 0.95rem; background: #fafbfc; transition: all 0.3s ease; }
        .form-control:focus { border-color: #1a3a6b; box-shadow: 0 0 0 4px rgba(26,58,107,0.12); background: white; }
        textarea.form-control { resize: vertical; font-family: 'Courier New', monospace; font-size: 0.82rem; }

        .btn-primary { background: linear-gradient(135deg, #1a3a6b, #0d1b3e); border: none; border-radius: 12px; padding: 13px 30px; font-weight: 700; color: white; transition: all 0.3s ease; box-shadow: 0 4px 15px rgba(26,58,107,0.3); }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 10px 30px rgba(26,58,107,0.5); color: white; }
        .btn-success { background: linear-gradient(135deg, #28a745, #20c997); border: none; border-radius: 12px; padding: 13px 30px; font-weight: 700; color: white; transition: all 0.3s ease; }
        .btn-success:hover { transform: translateY(-2px); box-shadow: 0 10px 30px rgba(40,167,69,0.4); color: white; }
        .btn-outline-secondary { border: 2px solid #e9ecef; border-radius: 12px; padding: 12px 26px; font-weight: 600; }
        .btn-outline-primary { border: 2px solid #1a3a6b; border-radius: 12px; padding: 12px 26px; font-weight: 600; color: #1a3a6b; }
        .btn-outline-primary:hover { background: #1a3a6b; color: white; }

        .alert { border-radius: 12px; border: none; padding: 16px 22px; margin-bottom: 22px; }
        .alert-success { background: #d4edda; color: #155724; border-left: 5px solid #28a745; }
        .alert-danger { background: #fde8e8; color: #721c24; border-left: 5px solid #dc3545; }
        .alert-info { background: #e7f3ff; color: #084298; border-left: 5px solid #0d6efd; }
        .alert-warning { background: #fff3cd; color: #856404; border-left: 5px solid #ffc107; }
        .alert i { margin-right: 8px; }

        .preview-table-wrap { overflow-x: auto; border-radius: 12px; border: 1px solid #e9ecef; max-height: 600px; overflow-y: auto; }
        .preview-table { width: 100%; font-size: 0.82rem; border-collapse: collapse; margin: 0; }
        .preview-table thead th { position: sticky; top: 0; background: #1a3a6b; color: white; padding: 12px 10px; font-weight: 600; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; white-space: nowrap; z-index: 10; }
        .preview-table tbody td { padding: 10px; border-bottom: 1px solid #f0f2f5; white-space: nowrap; }
        .preview-table tbody tr:hover { background: #f8f9fa; }
        .preview-table tbody tr.row-error { background: #fef2f2; }
        .preview-table tbody tr.row-error:hover { background: #fde8e8; }
        .preview-table .row-num { font-weight: 700; color: #6c757d; font-size: 0.75rem; }
        .preview-table .status-badge { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: 0.7rem; font-weight: 700; }
        .status-badge.ok { background: #d4edda; color: #155724; }
        .status-badge.err { background: #f8d7da; color: #721c24; }
        .status-badge.dup { background: #fff3cd; color: #856404; }
        .error-list { font-size: 0.7rem; color: #dc3545; margin: 0; padding-left: 15px; }

        .surname-cell {
            background: #e8f0fe;
            color: #0d6efd;
            padding: 2px 8px;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
        }

        .summary-bar { display: flex; gap: 15px; flex-wrap: wrap; margin-bottom: 20px; padding: 16px 20px; background: #f8f9fa; border-radius: 12px; border-left: 4px solid #1a3a6b; }
        .summary-item { display: flex; align-items: center; gap: 10px; }
        .summary-item .num { font-size: 1.4rem; font-weight: 800; }
        .summary-item .label { font-size: 0.82rem; color: #6c757d; font-weight: 600; }
        .summary-item.valid .num { color: #28a745; }
        .summary-item.error .num { color: #dc3545; }
        .summary-item.dup .num { color: #fd7e14; }

        .instructions-box { background: #f0f4fa; border-radius: 12px; padding: 20px 25px; margin-bottom: 20px; border-left: 4px solid #0d6efd; }
        .instructions-box h6 { font-weight: 700; color: #0d6efd; margin-bottom: 12px; }
        .instructions-box ol { margin: 0; padding-left: 20px; color: #495057; font-size: 0.9rem; }
        .instructions-box ol li { margin-bottom: 5px; }
        .instructions-box code { background: white; padding: 2px 8px; border-radius: 4px; color: #dc3545; font-size: 0.85rem; }

        .main-footer { background: white; padding: 15px 20px; border-top: 1px solid #e9ecef; text-align: center; font-size: 0.9rem; color: #6c757d; margin-left: 280px; }

        @media (min-width: 992px) {
            .main-sidebar { transform: translateX(0) !important; }
            .sidebar-toggle-btn, .sidebar-overlay { display: none !important; }
            .content-wrapper { margin-left: 280px; }
        }
        @media (max-width: 991.98px) {
            .sidebar-toggle-btn { display: block; }
            .main-sidebar.open { transform: translateX(0); }
            .content-wrapper { margin-left: 0; padding-top: 70px; }
            .sidebar-close-btn { display: block; }
            .main-footer { margin-left: 0; }
        }
        @media (max-width: 576px) {
            .content-header h1 { font-size: 1.4rem; }
            .card-body { padding: 20px 16px; }
            .btn-primary, .btn-success, .btn-outline-secondary, .btn-outline-primary { width: 100%; margin-bottom: 8px; }
            .upload-zone { padding: 30px 18px; }
            .upload-zone .upload-icon { font-size: 3rem; }
        }
        body.sidebar-open { overflow: hidden; }
    </style>
</head>
<body>

<button class="sidebar-toggle-btn" id="sidebarToggle"><i class="fas fa-bars"></i></button>
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<aside class="main-sidebar" id="mainSidebar">
    <button class="sidebar-close-btn" id="sidebarClose"><i class="fas fa-times"></i></button>
    <div class="sidebar-brand">
        <img src="../assets/images/logo.png" onerror="this.src='https://via.placeholder.com/45x45/ffffff/1a3a6b?text=PI'">
        <div class="brand-text">Dormitory MS<small>Admin Panel</small></div>
    </div>
    <div class="user-panel">
        <img src="https://ui-avatars.com/api/?name=Admin&background=ffd700&color=1a3a6b&size=42">
        <div><a href="#">Administrator</a><small>Admin</small></div>
    </div>
    <ul class="nav nav-pills nav-sidebar flex-column">
        <li class="nav-item"><a href="index.php" class="nav-link"><i class="nav-icon fas fa-tachometer-alt"></i><p>Dashboard</p></a></li>
        <li class="nav-item"><a href="students.php" class="nav-link"><i class="nav-icon fas fa-users"></i><p>Students</p></a></li>
          <li class="nav-item">
    <a href="manage-staff.php" class="nav-link">
        <i class="nav-icon fas fa-user-tie"></i> Manage Staff
    </a>
</li>
        <li class="nav-item"><a href="bulk-students.php" class="nav-link active"><i class="nav-icon fas fa-file-upload"></i><p>Bulk Upload</p></a></li>
        <li class="nav-item"><a href="rooms.php" class="nav-link"><i class="nav-icon fas fa-bed"></i><p>Rooms</p></a></li>
        <li class="nav-item"><a href="post-room.php" class="nav-link"><i class="nav-icon fas fa-plus-square"></i><p>Post Room</p></a></li>
        <li class="nav-item"><a href="hostels.php" class="nav-link"><i class="nav-icon fas fa-building"></i><p>Hostels</p></a></li>
        <li class="nav-item"><a href="allocations.php" class="nav-link"><i class="nav-icon fas fa-door-open"></i><p>Allocations</p></a></li>
        <li class="nav-item"><a href="payments.php" class="nav-link"><i class="nav-icon fas fa-credit-card"></i><p>Payments</p></a></li>
         <li class="nav-item has-treeview">
                <a href="#" class="nav-link">
                    <i class="nav-icon fas fa-clipboard-list"></i>
                    <p>
                        Requests
                        <i class="right fas fa-angle-left"></i>
                    </p>
                </a>
                <ul class="nav nav-treeview">
                    <li class="nav-item">
                        <a href="complaints.php" class="nav-link">
                            <i class="far fa-circle nav-icon"></i>
                            <p>Complaints</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="maintenance.php" class="nav-link">
                            <i class="far fa-circle nav-icon"></i>
                            <p>Maintenance</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="refunds.php" class="nav-link">
                            <i class="far fa-circle nav-icon"></i>
                            <p>Refunds</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="room-change.php" class="nav-link">
                            <i class="far fa-circle nav-icon"></i>
                            <p>Room Changes</p>
                        </a>
                    </li>
                </ul>
            </li>
        <li class="nav-item"><a href="reports.php" class="nav-link"><i class="nav-icon fas fa-chart-line"></i><p>Reports</p></a></li>
        <li class="nav-item"><a href="logs.php" class="nav-link"><i class="nav-icon fas fa-history"></i><p>Activity Logs</p></a></li>
        <li class="nav-item"><a href="logout.php" class="nav-link text-danger"><i class="nav-icon fas fa-sign-out-alt"></i><p>Logout</p></a></li>
    </ul>
</aside>

<div class="content-wrapper">
    <section class="content-header">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
            <h1><i class="fas fa-file-upload me-2"></i>Bulk Student Upload</h1>
            <div class="d-flex gap-2 flex-wrap">
                <a href="?download_template=1" class="btn btn-outline-primary">
                    <i class="fas fa-download me-2"></i>Download Template
                </a>
                <a href="students.php" class="btn btn-outline-secondary">
                    <i class="fas fa-users me-2"></i>View Students
                </a>
            </div>
        </div>
    </section>

    <!-- STEP INDICATOR -->
    <div class="step-indicator">
        <div class="step <?= $step === 'upload' ? 'active' : 'done' ?>">
            <span class="step-num">1</span>
            <span>Upload File</span>
        </div>
        <div class="step-line <?= in_array($step, ['preview', 'done']) ? 'done' : '' ?>"></div>
        <div class="step <?= $step === 'preview' ? 'active' : ($step === 'done' ? 'done' : '') ?>">
            <span class="step-num">2</span>
            <span>Preview</span>
        </div>
        <div class="step-line <?= $step === 'done' ? 'done' : '' ?>"></div>
        <div class="step <?= $step === 'done' ? 'done' : '' ?>">
            <span class="step-num">3</span>
            <span>Complete</span>
        </div>
    </div>

    <!-- ALERTS -->
    <?php if ($success): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i><?= $success ?>
            <div class="mt-2">
                <a href="students.php" class="btn btn-sm btn-success">
                    <i class="fas fa-users me-1"></i>View All Students
                </a>
                <a href="bulk-students.php" class="btn btn-sm btn-outline-secondary">
                    <i class="fas fa-plus me-1"></i>Import More
                </a>
            </div>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- ============================================ -->
    <!-- STEP 1: UPLOAD -->
    <!-- ============================================ -->
    <?php if ($step === 'upload'): ?>
        <div class="row g-4">
            <div class="col-lg-7">
                <div class="card card-primary">
                    <div class="card-header">
                        <h3><i class="fas fa-cloud-upload-alt me-2"></i>Upload CSV File</h3>
                    </div>
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data" id="uploadForm">
                            <div class="upload-zone" id="uploadZone" onclick="document.getElementById('csvFile').click()">
                                <input type="file" name="csv_file" id="csvFile" accept=".csv,.txt">
                                <div class="upload-icon"><i class="fas fa-file-csv"></i></div>
                                <h4>Click to select or drag & drop</h4>
                                <p>CSV file only • Max 5MB • Up to 1000 rows</p>
                                <div id="fileInfo" style="display:none; margin-top: 15px;">
                                    <div class="badge bg-success" style="padding: 8px 16px; font-size: 0.85rem;">
                                        <i class="fas fa-file-csv me-1"></i>
                                        <span id="fileName"></span>
                                    </div>
                                </div>
                            </div>

                            <div class="divider-text">OR PASTE DATA</div>

                            <div class="mb-3">
                                <label class="form-label fw-bold">
                                    <i class="fas fa-paste text-primary"></i>Paste CSV / Tab-separated Data
                                </label>
                                <textarea name="pasted_data" class="form-control" rows="6"
                                          placeholder="matric_no,full_name,surname,email,phone,gender,faculty,department,level,semester&#10;2024239010098,OKUEWU ODUNAYO GLORIA,OKUEWU,gloria@example.com,08012345678,Female,Science,Computer Science,HND2,1"></textarea>
                                <small class="text-muted">Copy from Excel, Google Sheets, or any CSV file</small>
                            </div>

                            <div class="d-flex gap-2 flex-wrap">
                                <button type="submit" name="parse_csv" class="btn btn-primary" id="parseBtn">
                                    <i class="fas fa-arrow-right me-2"></i>Parse & Preview
                                </button>
                                <button type="reset" class="btn btn-outline-secondary">
                                    <i class="fas fa-redo me-2"></i>Clear
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="instructions-box">
                    <h6><i class="fas fa-info-circle"></i> How to Prepare Your CSV</h6>
                    <ol>
                        <li>Download the <a href="?download_template=1" class="fw-bold">CSV template</a> above</li>
                        <li>Fill in student details — one row per student</li>
                        <li>Required columns (in this exact order):
                            <br><code>matric_no</code> <code>full_name</code> <code>surname</code>
                            <code>email</code> <code>phone</code> <code>gender</code>
                            <code>faculty</code> <code>department</code> <code>level</code> <code>semester</code>
                        </li>
                        <li>Save as <strong>CSV (Comma delimited)</strong></li>
                        <li>Upload the file above</li>
                    </ol>
                </div>

                <div class="instructions-box" style="background: #e7f3ff; border-left-color: #0d6efd;">
                    <h6 style="color: #0d6efd;"><i class="fas fa-sign-in-alt"></i> Login Credentials</h6>
                    <ul style="margin: 0; padding-left: 20px; font-size: 0.85rem; color: #084298;">
                        <li>Students log in with <strong>matric number</strong> + <strong>surname</strong></li>
                        <li><strong>Surname</strong> is a required column</li>
                        <li>If surname is missing, it will be auto-extracted from the first word of <code>full_name</code></li>
                        <li>Name format: <strong>SURNAME FIRSTNAME MIDDLENAME</strong></li>
                    </ul>
                </div>

                <div class="instructions-box" style="background: #fff3cd; border-left-color: #ffc107;">
                    <h6 style="color: #856404;"><i class="fas fa-lightbulb"></i> Format Tips</h6>
                    <ul style="margin: 0; padding-left: 20px; font-size: 0.85rem; color: #856404;">
                        <li><strong>Gender:</strong> Male, Female (or M, F)</li>
                        <li><strong>Level:</strong> ND1, ND2, HND1, HND2</li>
                        <li><strong>Semester:</strong> 1 or 2</li>
                        <li><strong>Faculty:</strong> Exact name (e.g., "Science")</li>
                        <li><strong>Department:</strong> Exact name (e.g., "Computer Science")</li>
                        <li><strong>Email:</strong> Unique per student</li>
                        <li><strong>Matric:</strong> Unique per student</li>
                    </ul>
                </div>

                <div class="alert alert-info">
                    <i class="fas fa-shield-alt"></i>
                    <strong>Safe Import:</strong> Students are imported <strong>without a password</strong>.
                    They log in with matric + surname. They can set a password later.
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- ============================================ -->
    <!-- STEP 2: PREVIEW -->
    <!-- ============================================ -->
    <?php if ($step === 'preview' && !empty($preview_data)):
        $total = count($preview_data);
        $valid_count = 0; $error_count = 0; $dup_count = 0;
        foreach ($preview_data as $p) {
            if (!empty($p['errors'])) {
                if (in_array('Already exists in database', $p['errors'])) $dup_count++;
                else $error_count++;
            } else $valid_count++;
        }
    ?>
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap">
                <h3><i class="fas fa-clipboard-check me-2"></i>Validation Preview</h3>
                <span class="badge bg-primary" style="padding: 8px 16px; font-size: 0.85rem;">
                    <?= $total ?> row(s) found
                </span>
            </div>
            <div class="card-body">
                <div class="summary-bar">
                    <div class="summary-item">
                        <i class="fas fa-database" style="font-size:1.5rem;color:#1a3a6b;"></i>
                        <div><div class="num" style="color:#1a3a6b;"><?= $total ?></div><div class="label">Total</div></div>
                    </div>
                    <div class="summary-item valid">
                        <i class="fas fa-check-circle" style="font-size:1.5rem;color:#28a745;"></i>
                        <div><div class="num"><?= $valid_count ?></div><div class="label">Ready</div></div>
                    </div>
                    <div class="summary-item error">
                        <i class="fas fa-times-circle" style="font-size:1.5rem;color:#dc3545;"></i>
                        <div><div class="num"><?= $error_count ?></div><div class="label">Errors</div></div>
                    </div>
                    <div class="summary-item dup">
                        <i class="fas fa-exclamation-triangle" style="font-size:1.5rem;color:#fd7e14;"></i>
                        <div><div class="num"><?= $dup_count ?></div><div class="label">Duplicates</div></div>
                    </div>
                </div>

                <?php if ($valid_count === 0): ?>
                    <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i><strong>No valid rows to import!</strong> Please fix the errors and try again.</div>
                <?php elseif ($error_count > 0 || $dup_count > 0): ?>
                    <div class="alert alert-warning"><i class="fas fa-exclamation-triangle"></i>
                        <strong>Attention:</strong> <?= $error_count ?> error(s), <?= $dup_count ?> duplicate(s). These will be skipped. Only <strong><?= $valid_count ?> valid row(s)</strong> will be imported.
                    </div>
                <?php else: ?>
                    <div class="alert alert-success"><i class="fas fa-check-circle"></i><strong>All <?= $valid_count ?> row(s) are valid!</strong></div>
                <?php endif; ?>

                <div class="preview-table-wrap">
                    <table class="preview-table">
                        <thead>
                            <tr>
                                <th>Row</th>
                                <th>Status</th>
                                <th>Matric No</th>
                                <th>Full Name</th>
                                <th>Surname</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Gender</th>
                                <th>Faculty</th>
                                <th>Department</th>
                                <th>Level</th>
                                <th>Sem</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($preview_data as $p):
                                $has_err = !empty($p['errors']);
                                $is_dup = in_array('Already exists in database', $p['errors']);
                            ?>
                                <tr class="<?= $has_err ? 'row-error' : '' ?>">
                                    <td class="row-num">#<?= $p['row'] ?></td>
                                    <td>
                                        <?php if (!$has_err): ?><span class="status-badge ok"><i class="fas fa-check"></i> OK</span>
                                        <?php elseif ($is_dup): ?><span class="status-badge dup"><i class="fas fa-exclamation-triangle"></i> DUP</span>
                                        <?php else: ?><span class="status-badge err"><i class="fas fa-times"></i> ERR</span><?php endif; ?>
                                        <?php if ($has_err): ?>
                                            <ul class="error-list mt-1">
                                                <?php foreach ($p['errors'] as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?>
                                            </ul>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($p['matric_no']) ?></td>
                                    <td><?= htmlspecialchars($p['full_name']) ?></td>
                                    <td><span class="surname-cell"><?= htmlspecialchars($p['surname']) ?></span></td>
                                    <td><?= htmlspecialchars($p['email']) ?></td>
                                    <td><?= htmlspecialchars($p['phone']) ?></td>
                                    <td><?= htmlspecialchars($p['gender']) ?></td>
                                    <td><?= htmlspecialchars($p['faculty_name']) ?></td>
                                    <td><?= htmlspecialchars($p['department_name']) ?></td>
                                    <td><?= htmlspecialchars($p['level']) ?></td>
                                    <td><?= htmlspecialchars($p['semester']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <form method="POST" class="mt-4" id="confirmForm">
                    <input type="hidden" name="rows_data" value="<?= htmlspecialchars(json_encode($preview_data)) ?>">
                    <div class="d-flex gap-2 flex-wrap">
                        <button type="submit" name="confirm_import" class="btn btn-success" id="confirmBtn"
                                <?= $valid_count === 0 ? 'disabled' : '' ?>>
                            <i class="fas fa-check-circle me-2"></i>
                            Confirm Import (<?= $valid_count ?>)
                        </button>
                        <a href="bulk-students.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Back
                        </a>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>
</div>

<footer class="main-footer">
    <strong>Dormitory Management System</strong> &copy; <?= date('Y') ?> | The Polytechnic Ibadan
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('mainSidebar');
    const toggleBtn = document.getElementById('sidebarToggle');
    const closeBtn = document.getElementById('sidebarClose');
    const overlay = document.getElementById('sidebarOverlay');
    function closeSidebar() { sidebar.classList.remove('open'); overlay.classList.remove('active'); document.body.style.overflow = ''; }
    if (toggleBtn) toggleBtn.addEventListener('click', () => {
        sidebar.classList.toggle('open'); overlay.classList.toggle('active');
        document.body.style.overflow = sidebar.classList.contains('open') ? 'hidden' : '';
    });
    if (closeBtn) closeBtn.addEventListener('click', closeSidebar);
    if (overlay) overlay.addEventListener('click', closeSidebar);

    // ===== FILE UPLOAD =====
    const uploadZone = document.getElementById('uploadZone');
    const csvFile = document.getElementById('csvFile');
    const fileInfo = document.getElementById('fileInfo');
    const fileName = document.getElementById('fileName');

    if (csvFile) {
        csvFile.addEventListener('change', function() {
            if (this.files[0]) {
                fileName.textContent = this.files[0].name + ' (' + (this.files[0].size / 1024).toFixed(1) + ' KB)';
                fileInfo.style.display = 'block';
            } else fileInfo.style.display = 'none';
        });
        ['dragenter', 'dragover'].forEach(e => uploadZone.addEventListener(e, ev => { ev.preventDefault(); uploadZone.classList.add('dragover'); }));
        ['dragleave', 'drop'].forEach(e => uploadZone.addEventListener(e, ev => { ev.preventDefault(); uploadZone.classList.remove('dragover'); }));
        uploadZone.addEventListener('drop', e => {
            if (e.dataTransfer.files.length > 0) {
                csvFile.files = e.dataTransfer.files;
                csvFile.dispatchEvent(new Event('change'));
            }
        });
    }

    // ===== FORM LOADING =====
    const uploadForm = document.getElementById('uploadForm');
    const parseBtn = document.getElementById('parseBtn');
    if (uploadForm) {
        uploadForm.addEventListener('submit', function() {
            parseBtn.disabled = true;
            parseBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Parsing...';
        });
    }

    // ===== CONFIRM IMPORT =====
    const confirmForm = document.getElementById('confirmForm');
    const confirmBtn = document.getElementById('confirmBtn');
    if (confirmForm) {
        confirmForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const count = confirmBtn.textContent.match(/\d+/);
            const num = count ? count[0] : 'these';
            Swal.fire({
                title: 'Confirm Import?',
                html: `You are about to import <strong>${num}</strong> student(s) into the database.`,
                icon: 'question', showCancelButton: true,
                confirmButtonText: '<i class="fas fa-check me-2"></i>Yes, Import',
                cancelButtonText: 'Cancel',
                confirmButtonColor: '#28a745', cancelButtonColor: '#6c757d'
            }).then(result => {
                if (result.isConfirmed) {
                    confirmBtn.disabled = true;
                    confirmBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Importing...';
                    confirmForm.submit();
                }
            });
        });
    }
});
</script>
</body>
</html>