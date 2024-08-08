<?php

namespace Hosni\EasytoyouApi\Account;

class Account
{
    protected string $username;
    protected string $password;

    public static function make(string $username, string $password): self
    {
        return new self($username, $password);
    }

    protected function __construct(string $username, string $password)
    {
        if (!$username) {
            throw new \InvalidArgumentException('The username should not be empty!');
        }
        if (!$password) {
            throw new \InvalidArgumentException('The password should not be empty!');
        }
        $this->username = $username;
        $this->password = $password;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function getPassword(): string
    {
        return $this->password;
    }
}
