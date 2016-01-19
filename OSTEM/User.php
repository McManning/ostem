<?php

namespace OSTEM;

class User {
    
    public function __construct(\PDO $db, $email, $role) 
    {
        $this->db = $db;
        $this->email = $email;
        $this->role = $role;
    }

    public function updatePassword($password) 
    {
        try {
            $statement = $this->db->prepare("
                UPDATE 
                    users
                SET 
                    password=:password
                WHERE
                    email=:email
            ");

            $statement->execute(array(
                'email' => $this->email,
                'password' => password_hash($password, PASSWORD_DEFAULT)
            ));
            
        } catch (\PDOException $e) {
            die(sprintf('DB error: %s', $e->getMessage()));
            // TODO: Log something
            return false;
        }

        return true;
    }
}
