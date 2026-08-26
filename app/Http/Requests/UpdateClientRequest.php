<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class UpdateClientRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepare the data for validation.
     * Normalize or transform incoming data before validation.
     */
    protected function prepareForValidation()
    {
        if ($this->has('accounts')) {
            $accounts = $this->input('accounts');

            foreach ($accounts as &$account) {
                if (isset($account['status'])) {
                    $account['status'] = strtoupper(trim($account['status']));
                }
            }

            // Reindex and merge back
            $this->merge(['accounts' => array_values($accounts)]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        // Get current valid status codes from database
        $validStatusCodes = DB::table('status_code')
            ->pluck('code')
            ->map(fn ($code) => strtoupper(trim($code)))
            ->toArray();

        $rules = [
            'name' => 'nullable|string|max:255',
            'email' => 'nullable|string',
            'password' => 'nullable|min:8|confirmed',
            'confirm_password' => 'nullable|same:password',
            'contact_no' => 'nullable|string',
            'registrant_id' => 'nullable|exists:concessioner_accounts,id',
            'connection_type' => ['nullable', Rule::in(['on_line', 'traverse'])],

            'accounts' => 'nullable|array',
        ];

        foreach ($this->input('accounts', []) as $index => $account) {
            $isSelectedRegistrant = $this->filled('registrant_id')
                && (string) ($account['id'] ?? '') === (string) $this->input('registrant_id');
            $accountRule = $isSelectedRegistrant ? 'required' : 'nullable';
            $prefix = "accounts.{$index}.";

            $rules[$prefix . 'id'] = 'nullable|exists:concessioner_accounts,id';
            $rules[$prefix . 'account_no'] = $accountRule . '|string';
            $rules[$prefix . 'address'] = $accountRule . '|string|max:255';
            $rules[$prefix . 'property_type'] = $accountRule . '|exists:property_types,id';
            $rules[$prefix . 'rate_code'] = $accountRule . '|numeric|gt:0';
            $rules[$prefix . 'status'] = [$accountRule, Rule::in($validStatusCodes)];
            $rules[$prefix . 'sc_no'] = $accountRule . '|string';
            $rules[$prefix . 'meter_brand'] = 'nullable|string|max:256';
            $rules[$prefix . 'meter_serial_no'] = $accountRule . '|string';
            $rules[$prefix . 'date_connected'] = $accountRule . '|date';
            $rules[$prefix . 'sequence_no'] = $accountRule . '|string';
            $rules[$prefix . 'meter_type'] = 'nullable|string|max:120';
            $rules[$prefix . 'meter_wire'] = 'nullable|string|max:120';
            $rules[$prefix . 'meter_form'] = 'nullable|string|max:120';
            $rules[$prefix . 'meter_class'] = 'nullable|string|max:120';
            $rules[$prefix . 'lat_long'] = 'nullable|string|max:120';
            $rules[$prefix . 'isErcSealed'] = 'nullable|boolean';
            $rules[$prefix . 'inspectionImage'] = 'nullable|image|mimes:jpg,png,jpeg,gif|max:2048';
            $rules[$prefix . 'has_sc_discount'] = ['nullable', 'boolean'];
            $rules[$prefix . 'sc_id_no'] = ['nullable'];
        }

        return $rules;

    }

    /**
     * Custom error messages for validation failures.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'The name field is required.',
            'email.required' => 'The email field is required.',
            'email.email' => 'Please provide a valid email address.',
            'email.unique' => 'This email is already in use.',
            'password.min' => 'The password must be at least 8 characters long.',
            'confirm_password.same' => 'The confirm password must match the password.',
            'contact_no.required' => 'The contact number field is required.',

            'accounts.required' => 'At least one account must be provided.',
            'accounts.array' => 'Accounts must be a valid array.',

            'accounts.*.account_no.required' => 'The account number is required.',
            'accounts.*.address.required' => 'The address is required.',
            'accounts.*.property_type.required' => 'The property type is required.',
            'accounts.*.property_type.exists' => 'The selected property type does not exist.',
            'accounts.*.rate_code.required' => 'The rate code is required.',
            'accounts.*.rate_code.numeric' => 'The rate code must be a number.',
            'accounts.*.rate_code.gt' => 'The rate code must be greater than 0.',

            'accounts.*.status.required' => 'The status field is required.',
            'accounts.*.status.in' => 'The selected status is invalid. Please choose a valid status from the list.',

            'accounts.*.sc_no.required' => 'The SC number is required.',
            'accounts.*.meter_serial_no.required' => 'The meter serial number is required.',
            'accounts.*.date_connected.required' => 'The date connected field is required.',
            'accounts.*.sequence_no.required' => 'The sequence number field is required.',

            'accounts.*.inspectionImage.image' => 'The uploaded file must be an image.',
            'accounts.*.inspectionImage.mimes' => 'The image must be a JPG, PNG, JPEG, or GIF file.',
        ];
    }
}
