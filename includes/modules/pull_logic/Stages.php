<?php

class FetchCase {
    public function __invoke($data) {
        global $conn; // Access your DB connection from config.php
        
        $id = $conn->real_escape_string($data['id']);
        
        // Example: Check if the case exists in the database
        $result = $conn->query("SELECT * FROM attendance WHERE id = '$id'");
        
        if ($result->num_rows == 0) {
            throw new Exception("Case ID $id not found in database.");
        }

        $data['details'] = $result->fetch_assoc();
        echo "LOG: Case data retrieved for " . $data['details']['username'] . "<br>";
        
        return $data;
    }
}

class SaveCase {
    public function __invoke($data) {
        global $conn;
        
        $id = $conn->real_escape_string($data['id']);
        
        // Example: Update status to 'Pulled' (or whatever logic your portal needs)
        $sql = "UPDATE attendance SET status = 'pulled', last_updated = NOW() WHERE id = '$id'";
        
        if ($conn->query($sql)) {
            echo "LOG: Database updated successfully for Case $id.<br>";
        } else {
            throw new Exception("Database update failed: " . $conn->error);
        }
        
        return $data;
    }
}