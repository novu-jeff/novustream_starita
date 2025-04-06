<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

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
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $id = $this->route('concessionaire');
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|unique:users,email,' . $id,
            'password' => 'nullable|min:8',
            'confirm_password' => 'nullable|same:password',
            'contact_no' => 'required',

            'accounts' => 'required|array',
            'accounts.*.id' => 'nullable|exists:concessioner_accounts,id',
            'accounts.*.account_no' => 'required|string',
            'accounts.*.address' => 'required|string|max:255',
            'accounts.*.property_type' => 'required|exists:property_types,id',
            'accounts.*.rate_code' => 'required|numeric|gt:0',
            'accounts.*.status' => 'required|in:AB,BL,ID,IV',
            'accounts.*.sc_no' => 'required',
            'accounts.*.meter_brand' => 'nullable|string|max:256',
            'accounts.*.meter_serial_no' => 'required',
            'accounts.*.date_connected' => 'required',
            'accounts.*.sequence_no' => 'required',

            'accounts.*.meter_type' => 'nullable|string|max:120',
            'accounts.*.meter_wire' => 'nullable|string|max:120',
            'accounts.*.meter_form' => 'nullable|string|max:120',
            'accounts.*.meter_class' => 'nullable|string|max:120',
            'accounts.*.lat_long' => 'nullable|string|max:120',
            'accounts.*.isErcSealed' => 'nullable',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'The name field is required.',
            'name.string' => 'The name must be a valid string.',
            'name.max' => 'The name must not exceed 255 characters.',
            
            'address.required' => 'The address field is required.',
            'address.string' => 'The address must be a valid string.',
            'address.max' => 'The address must not exceed 255 characters.',

            'email.required' => 'The email field is required.',
            'email.email' => 'Please provide a valid email address.',
            'email.unique' => 'This email is already in use.',

            'password.min' => 'The password must be at least 8 characters long.',

            'confirm_password.same' => 'The confirm password must match the password.',

            'contact_no.required' => 'The contact number field is required.',

            'accounts.required' => 'At least one account must be provided.',
            'accounts.array' => 'Accounts must be in a valid array format.',

            'accounts.*.id.exists' => 'The selected account ID does not exist.',
            'accounts.*.account_no.required' => 'The account number is required.',
            'accounts.*.property_type.required' => 'The property type is required.',
            'accounts.*.property_type.exists' => 'The selected property type does not exist.',
            'accounts.*.rate_code.required' => 'The rate code is required.',
            'accounts.*.rate_code.numeric' => 'The rate code must be a valid number.',
            'accounts.*.rate_code.gt' => 'The rate code must be greater than 0.',
            'accounts.*.status.required' => 'The status field is required.',
            'accounts.*.status.in' => 'The status must be one of the following: AB, BL, ID, IV.',
            'accounts.*.sc_no.required' => 'The SC number is required.',
            'accounts.*.meter_brand.string' => 'The meter brand must be a valid string.',
            'accounts.*.meter_brand.max' => 'The meter brand must not exceed 256 characters.',
            'accounts.*.meter_serial_no.required' => 'The meter serial number is required.',
            'accounts.*.date_connected.required' => 'The date connected field is required.',
            'accounts.*.sequence_no.required' => 'The sequence number field is required.',

            'accounts.*.meter_type.string' => 'The meter type must be a valid string.',
            'accounts.*.meter_type.max' => 'The meter type must not exceed 120 characters.',
            'accounts.*.meter_wire.string' => 'The meter wire must be a valid string.',
            'accounts.*.meter_wire.max' => 'The meter wire must not exceed 120 characters.',
            'accounts.*.meter_form.string' => 'The meter form must be a valid string.',
            'accounts.*.meter_form.max' => 'The meter form must not exceed 120 characters.',
            'accounts.*.meter_class.string' => 'The meter class must be a valid string.',
            'accounts.*.meter_class.max' => 'The meter class must not exceed 120 characters.',
            'accounts.*.lat_long.string' => 'The latitude/longitude must be a valid string.',
            'accounts.*.lat_long.max' => 'The latitude/longitude must not exceed 120 characters.',
        ];
    }
}
