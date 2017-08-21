<?php

namespace App\Provider;

use App\Code\StatusCode;
use App\Connector\MySQL;
use App\Type\UserType;
use Symfony\Component\HttpFoundation\Session\Session;

class User
{
    private $id;
    private $email;
    private $username;
    private $firstName;
    private $lastName;
    private $parentFirstName;
    private $parentLastName;
    private $parentEmail;
    private $role;

    public static function create($id, $email, $username, $firstName, $lastName, $parentFirstName, $parentLastName, $parentEmail, $role)
    {
        $object = new User();

        $object->setId($id);
        $object->setEmail($email);
        $object->setUsername($username);
        $object->setFirstName($firstName);
        $object->setLastName($lastName);
        $object->setParentFirstName($parentFirstName);
        $object->setParentLastName($parentLastName);
        $object->setParentEmail($parentEmail);
        $object->setRole($role);

        return $object;
    }

    public static function load($email, $password, $superLoad = false)
    {
        $sql = 'SELECT * FROM users WHERE email = :e';
        $user = MySQL::get()->fetchOne($sql, ['e' => $email]);

        if (!$superLoad)
        {
            if (!$user) return StatusCode::USER_BAD_CREDENTIALS;
            if (!password_verify($password, $user['password'])) return StatusCode::USER_BAD_CREDENTIALS;
        }

        return User::create(
            $user['id'],
            $user['email'],
            $user['username'],
            $user['first_name'],
            $user['last_name'],
            $user['parent_first_name'],
            $user['parent_last_name'],
            $user['parent_email'],
            $user['role']
        );
    }

    public function serialize()
    {
        $result = $this->convertToArray();
        $result = json_encode($result);
        return $result;
    }

    public static function unserialize($data)
    {
        $data = json_decode($data, true);
        return User::create(
            $data['id'],
            $data['email'],
            $data['username'],
            $data['first_name'],
            $data['last_name'],
            $data['parent_first_name'],
            $data['parent_last_name'],
            $data['parent_email'],
            $data['role']
        );
    }

    public function convertToArray()
    {
        $result = [
            'id' => $this->getId(),
            'email' => $this->getEmail(),
            'username' => $this->getUsername(),
            'first_name' => $this->getFirstName(),
            'last_name' => $this->getLastName(),
            'parent_first_name' => $this->getParentFirstName(),
            'parent_last_name' => $this->getParentLastName(),
            'parent_email' => $this->getParentEmail(),
            'role' => $this->getRole()
        ];

        return $result;
    }

    // get balance from db, not store in session
    public function getBalance()
    {
        return Model::get('transaction_history')->getBalance($this->getId());
    }

    // BELOW STARTS GENERATED GET/SET

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param mixed $id
     */
    public function setId($id)
    {
        $this->id = $id;
    }

    /**
     * @return mixed
     */
    public function getEmail()
    {
        return $this->email;
    }

    /**
     * @param mixed $email
     */
    public function setEmail($email)
    {
        $this->email = $email;
    }

    public function getFullName()
    {
        return $this->getFirstName() . ' ' . $this->getLastName();
    }

    /**
     * @return mixed
     */
    public function getFirstName()
    {
        return $this->firstName;
    }

    /**
     * @param mixed $firstName
     */
    public function setFirstName($firstName)
    {
        $this->firstName = $firstName;
    }

    /**
     * @return mixed
     */
    public function getLastName()
    {
        return $this->lastName;
    }

    /**
     * @param mixed $lastName
     */
    public function setLastName($lastName)
    {
        $this->lastName = $lastName;
    }

    /**
     * @return mixed
     */
    public function getParentFirstName()
    {
        return $this->parentFirstName;
    }

    /**
     * @param mixed $parentFirstName
     */
    public function setParentFirstName($parentFirstName)
    {
        $this->parentFirstName = $parentFirstName;
    }

    /**
     * @return mixed
     */
    public function getParentLastName()
    {
        return $this->parentLastName;
    }

    /**
     * @param mixed $parentLastName
     */
    public function setParentLastName($parentLastName)
    {
        $this->parentLastName = $parentLastName;
    }

    /**
     * @return mixed
     */
    public function getParentEmail()
    {
        return $this->parentEmail;
    }

    /**
     * @param mixed $parentEmail
     */
    public function setParentEmail($parentEmail)
    {
        $this->parentEmail = $parentEmail;
    }

    /**
     * @return mixed
     */
    public function getRole()
    {
        return $this->role;
    }

    /**
     * @param mixed $role
     */
    public function setRole($role)
    {
        $this->role = $role;
    }

    /**
     * @return mixed
     */
    public function getUsername()
    {
        return $this->username;
    }

    /**
     * @param mixed $username
     */
    public function setUsername($username)
    {
        $this->username = $username;
    }
}


