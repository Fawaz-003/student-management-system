<?php
include 'head.php';
include 'db_connect.php';

// Retrieve form data
$branch = $_POST['branch'] ?? '';
$year = $_POST['year'] ?? '';
$section = $_POST['section'] ?? '';
$semester = $_POST['semester'] ?? '';
$subject = $_POST['subject'] ?? '';
$exam = $_POST['exam'] ?? '';

// Fetch students based on branch, year, and section and sort by register number
$query = "SELECT roll_no, reg_no, name FROM students WHERE branch = ? AND year = ? AND section = ? ORDER BY reg_no";
$stmt = $conn->prepare($query);
$stmt->bind_param("sss", $branch, $year, $section);
$stmt->execute();
$result = $stmt->get_result();

$students = [];
while ($row = $result->fetch_assoc()) {
    $students[] = $row;
}

$stmt->close();

// Fetch previous marks if they exist
$previousMarksQuery = "SELECT roll_no, marks FROM marks WHERE branch = ? AND year = ? AND section = ? AND semester = ? AND subject = ? AND exam = ?";
$previousMarksStmt = $conn->prepare($previousMarksQuery);
$previousMarksStmt->bind_param("ssssss", $branch, $year, $section, $semester, $subject, $exam);
$previousMarksStmt->execute();
$previousMarksResult = $previousMarksStmt->get_result();

$previousMarks = [];
while ($row = $previousMarksResult->fetch_assoc()) {
    $previousMarks[$row['roll_no']] = $row['marks'];
}

$previousMarksStmt->close();

// Check if the form is submitted
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['marks'])) {
    // Validate required POST data
    if (!empty($semester) && !empty($subject) && !empty($exam) && isset($_POST['marks'])) {
        $marks = $_POST['marks']; // Array of roll_no => marks

        // Bind parameters and execute the statement
        $stmt = $conn->prepare("
            INSERT INTO marks (roll_no, subject, exam, marks, semester, branch, year, section)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                marks = VALUES(marks),
                semester = VALUES(semester),
                branch = VALUES(branch),
                year = VALUES(year),
                section = VALUES(section)
        ");

        if (!$stmt) {
            die("Prepare failed: " . $conn->error);
        }

        // Bind parameters as strings
        $stmt->bind_param("ssssssss", $roll_no, $subject, $exam, $mark, $semester, $branch, $year, $section);

        // Iterate through the marks array and execute the statement
        foreach ($marks as $roll_no => $mark) {
            // Skip students with no marks entered
            if (trim($mark) === "") {
                continue;
            }

            $roll_no = htmlspecialchars($roll_no);
            $mark = htmlspecialchars($mark); // Treat mark as a string

            $stmt->execute();

            if ($stmt->error) {
                echo "Error for Roll No $roll_no: " . $stmt->error . "<br>";
            }
        }
        $stmt->close();

        // Display success message and redirect
        echo "<script>
                alert('Marks entered successfully!');
                window.location.href = 'faculty_dashboard.php';
              </script>";
        exit();
    } else {
        echo "<script>alert('Missing required fields.');</script>";
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mark Entry</title>
    <style>
        /* General reset and font style */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #a8c0ff, #3f7cac); /* Soft pastel gradient */
            color: #333;
            padding: 40px;
        }

        h2 {
            text-align: center;
            color: #2e3b5e; /* Dark blue */
            font-size: 28px;
            margin-bottom: 15px;
            text-shadow: 1px 1px 3px rgba(0, 0, 0, 0.2);
        }

        h3 {
            text-align: center;
            color: #5072a7; /* Soft blue */
            font-size: 20px;
            margin-bottom: 20px;
        }

        /* Form Container */
        form {
            background-color: #fff; /* White background */
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.1);
            max-width: 800px; /* Slightly reduced width for better balance */
            margin: 0 auto;
        }

        /* Table Styling */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }

        table th, table td {
            padding: 12px;
            text-align: left;
            border: 1px solid #ddd;
            color: #555;
        }

        table th {
            background-color: #5072a7; /* Soft blue header */
            color: white;
            text-transform: uppercase;
        }

        table tr:nth-child(even) {
            background-color: #f9f9f9; /* Very light gray for even rows */
        }

        table tr:hover {
            background-color: #e0f3ff; /* Light blue hover effect */
        }

        /* Input Fields */
        input[type="text"] {
            width: 100%;
            padding: 12px;
            border-radius: 8px;
            border: 2px solid #5072a7; /* Soft blue border */
            margin: 8px 0;
            background-color: #fafafa;
            transition: border-color 0.3s ease, background-color 0.3s ease;
        }

        input[type="text"]:focus {
            border-color: #3f7cac; /* Darker blue on focus */
            outline: none;
            background-color: #fff;
        }

        input[type="text"]:disabled {
            background-color: #e0e0e0; /* Grey background for disabled inputs */
            color: #888; /* Grey text color for disabled inputs */
        }

        /* Button Styling */
        button {
            width: 100%;
            padding: 15px;
            background-color: #3f7cac; /* Soft teal button */
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 18px;
            transition: background-color 0.3s ease, transform 0.2s ease;
        }

        button:hover {
            background-color: #a8c0ff; /* Light blue hover effect */
            transform: scale(1.05); /* Slight zoom effect on hover */
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            h2 {
                font-size: 22px;
            }

            h3 {
                font-size: 18px;
            }

            table th, table td {
                font-size: 14px;
            }

            button {
                padding: 12px;
                font-size: 16px;
            }
        }

        /* Input Placeholder Text */
        ::placeholder {
            color: #aaa;
        }
    </style>
    <script>
        function previewMarks() {
            // Show a preview of the entered data
            const marksTable = document.querySelector('table tbody');
            let previewData = 'Roll Number | Register Number | Name | Marks\n';
            previewData += '================================\n';
            marksTable.querySelectorAll('tr').forEach(row => {
                const rollNo = row.cells[0].innerText;
                const regNo = row.cells[1].innerText;
                const name = row.cells[2].innerText;
                const marks = row.cells[3].querySelector('input').value;
                previewData += `${rollNo} | ${regNo} | ${name} | ${marks}\n`;
            });

            return confirm(`Preview of entered data:\n\n${previewData}\n\nPlease confirm that all entries are correct. Once submitted, this data cannot be modified.\n\nPress OK to proceed or Cancel to review.`);
        }

        function confirmSubmit() {
            // Ensure that all marks are entered
            const marksTable = document.querySelector('table tbody');
            let allMarksEntered = true;
            marksTable.querySelectorAll('tr').forEach(row => {
                const marks = row.cells[3].querySelector('input').value;
                if (marks.trim() === "") {
                    allMarksEntered = false;
                }
            });

            if (!allMarksEntered) {
                alert("Please ensure that marks are entered for all students.");
                return false;
            }

            // Make all input fields readonly
            document.querySelectorAll('input[type="text"]').forEach(input => {
                input.readOnly = true;
            });

            alert("Once submitted, the data cannot be modified.");
            return true; // Allow form submission
        }
    </script>
</head>
<body>

    <h2>Mark Entry for <?= htmlspecialchars($branch) ?> - <?= htmlspecialchars($year) ?> Year - Section <?= htmlspecialchars($section) ?></h2>
    <h3>Subject: <?= htmlspecialchars($subject) ?> | Exam: <?= htmlspecialchars($exam) ?></h3>

    <form method="post" action="" onsubmit="return confirmSubmit();">
        <!-- Hidden inputs to retain form details -->
        <input type="hidden" name="branch" value="<?= htmlspecialchars($branch) ?>">
        <input type="hidden" name="year" value="<?= htmlspecialchars($year) ?>">
        <input type="hidden" name="section" value="<?= htmlspecialchars($section) ?>">
        <input type="hidden" name="semester" value="<?= htmlspecialchars($semester) ?>"> 
        <input type="hidden" name="subject" value="<?= htmlspecialchars($subject) ?>">
        <input type="hidden" name="exam" value="<?= htmlspecialchars($exam) ?>">

        <!-- Table to input marks -->
        <table>
            <thead>
                <tr>
                    <th>Roll Number</th>
                    <th>Register Number</th>
                    <th>Name</th>
                    <th>Marks</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($students as $student): ?>
                    <tr>
                        <td><?= htmlspecialchars($student['roll_no']) ?></td>
                        <td><?= htmlspecialchars($student['reg_no']) ?></td>
                        <td><?= htmlspecialchars($student['name']) ?></td>
                        <td>
                            <input 
                                type="text" 
                                name="marks[<?= htmlspecialchars($student['roll_no']) ?>]" 
                                placeholder="Enter marks" 
                                value="<?= isset($previousMarks[$student['roll_no']]) ? htmlspecialchars($previousMarks[$student['roll_no']]) : '' ?>"
                                <?= isset($previousMarks[$student['roll_no']]) ? 'disabled' : '' ?>
                                required
                            >
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <button type="button" onclick="if(previewMarks()) { confirmSubmit(); }">Preview Marks</button>
        <button type="submit">Submit Marks</button>
    </form>
    
</body>
</html>
