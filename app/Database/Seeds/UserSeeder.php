<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;
use CodeIgniter\Shield\Models\UserModel;

class UserSeeder extends Seeder
{
    public function run()
    {
        $users = new UserModel();
        $db = \Config\Database::connect();

        $ensureGroup = static function (int $userId, string $group, string $label) use ($db): void {
            $groupExists = $db->table('auth_groups_users')
                ->where('user_id', $userId)
                ->where('group', $group)
                ->get()
                ->getFirstRow();

            if (! $groupExists) {
                $db->table('auth_groups_users')->insert([
                    'user_id' => $userId,
                    'group' => $group,
                    'created_at' => date('Y-m-d H:i:s'),
                ]);

                echo "Added missing group '{$group}' for {$label}\n";
            }
        };

        $createUser = function (string $username, string $email, string $password, string $group) use ($users, $db, $ensureGroup): void {
            $identity = $db->table('auth_identities')
                ->where('type', 'email_password')
                ->where('secret', $email)
                ->get()
                ->getFirstRow();

            if ($identity) {
                echo "Skipping existing user: {$email}\n";
                $ensureGroup((int) $identity->user_id, $group, $email);
                return;
            }

            $existingUser = $db->table('users')
                ->where('username', $username)
                ->get()
                ->getFirstRow();

            if ($existingUser) {
                echo "Skipping existing username: {$username}\n";
                $ensureGroup((int) $existingUser->id, $group, $username);
                return;
            }

            $users->insert([
                'username' => $username,
                'active' => 1,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            $userId = $users->getInsertID();

            $db->table('auth_identities')->insert([
                'user_id' => $userId,
                'type' => 'email_password',
                'secret' => $email,
                'secret2' => password_hash($password, PASSWORD_DEFAULT),
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            $db->table('auth_groups_users')->insert([
                'user_id' => $userId,
                'group' => $group,
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            echo "Created {$group} user: {$email}\n";
        };

        $createUser('admin', 'admin@fkmp.uthm.edu.my', 'Admin@12345', 'admin');
        $createUser('piclab01', 'pic.lab01@fkmp.uthm.edu.my', 'Pic@12345', 'pic');
        $createUser('manager01', 'manager01@fkmp.uthm.edu.my', 'Manager@12345', 'manager');
        $createUser('tech01', 'tech01@fkmp.uthm.edu.my', 'Tech@12345', 'technician');
        $createUser('d1230042', 'd1230042@student.uthm.edu.my', 'Student@12345', 'student');
        $createUser('external01', 'external01@example.com', 'External@12345', 'external');

        echo "User seeding completed.\n";
    }
}