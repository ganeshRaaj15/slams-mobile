<?php

namespace Config;

use App\Validation\UserRules;
use CodeIgniter\Shield\Authentication\Passwords;
use CodeIgniter\Config\BaseConfig;
use CodeIgniter\Validation\StrictRules\CreditCardRules;
use CodeIgniter\Validation\StrictRules\FileRules;
use CodeIgniter\Validation\StrictRules\FormatRules;
use CodeIgniter\Validation\StrictRules\Rules;

class Validation extends BaseConfig
{
    // --------------------------------------------------------------------
    // Setup
    // --------------------------------------------------------------------

    /**
     * Stores the classes that contain the
     * rules that are available.
     *
     * @var list<string>
     */
    public array $ruleSets = [
        Rules::class,
        FormatRules::class,
        FileRules::class,
        CreditCardRules::class,
        UserRules::class,
    ];

    /**
     * Specifies the views that are used to display the
     * errors.
     *
     * @var array<string, string>
     */
    public array $templates = [
        'list'   => 'CodeIgniter\Validation\Views\list',
        'single' => 'CodeIgniter\Validation\Views\single',
    ];

    // --------------------------------------------------------------------
    // Rules
    // --------------------------------------------------------------------

    public array $registration = [];

    public function __construct()
    {
        parent::__construct();

        $auth = config('Auth');
        $usernameRules = $auth->usernameValidationRules;
        $usernameRules['rules'][] = 'reusable_username';
        $usernameRules['errors']['reusable_username'] = 'That username is already in use.';

        $emailRules = $auth->emailValidationRules;
        $emailRules['rules'][] = 'is_unique[' . $auth->tables['identities'] . '.secret]';

        $this->registration = [
            'username' => $usernameRules,
            'email' => $emailRules,
            'password' => [
                'label' => 'Auth.password',
                'rules' => [
                    'required',
                    Passwords::getMaxLengthRule(),
                    'strong_password[]',
                ],
                'errors' => [
                    'max_byte' => 'Auth.errorPasswordTooLongBytes',
                ],
            ],
            'password_confirm' => [
                'label' => 'Auth.passwordConfirm',
                'rules' => 'required|matches[password]',
            ],
        ];
    }
}
