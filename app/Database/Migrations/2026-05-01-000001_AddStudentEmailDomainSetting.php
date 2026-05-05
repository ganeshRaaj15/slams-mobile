<?php

namespace App\Database\Migrations;

use App\Libraries\StudentRoleService;
use CodeIgniter\Database\Migration;

class AddStudentEmailDomainSetting extends Migration
{
    public function up()
    {
        $builder = $this->db->table('settings');

        $exists = $builder
            ->where('class', 'system')
            ->where('key', 'student_email_domain')
            ->countAllResults();

        if ($exists > 0) {
            return;
        }

        $now = date('Y-m-d H:i:s');

        $builder->insert([
            'class'      => 'system',
            'key'        => 'student_email_domain',
            'value'      => StudentRoleService::DEFAULT_STUDENT_EMAIL_DOMAIN,
            'type'       => 'string',
            'context'    => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down()
    {
        $this->db->table('settings')
            ->where('class', 'system')
            ->where('key', 'student_email_domain')
            ->delete();
    }
}
