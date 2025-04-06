<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreClientRequest extends FormRequest
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
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|unique:users,email',
            'password' => 'required|min:8',
            'contact_no' => 'required',
            'confirm_password' => 'required|same:password',

            'accounts' => 'required|array',
            'accounts.*.account_no' => 'required|unique:concessioner_accounts,account_no',
            'accounts.*.property_type' => 'required|exists:property_types,id',
            'accounts.*.address' => 'required|string|max:255',
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

    public function messages()
    {
        return [
            'accounts.required' => 'Atleast one account is required',
            'accounts.*.account_no.required' => 'The account number is required.',
            'accounts.*.account_no.unique' => 'The account number must be unique.',
            'accounts.*.property_type.required' => 'The property type is required.',
            'accounts.*.property_type.exists' => 'The selected property type is invalid.',
            'accounts.*.address.required' => 'The address is required.',
            'accounts.*.rate_code.required' => 'The rate code is required.',
            'accounts.*.rate_code.numeric' => 'The rate code must be a number.',
            'accounts.*.rate_code.gt' => 'The rate code must be greater than 0.',
            'accounts.*.status.required' => 'The status field is required.',
            'accounts.*.status.in' => 'The status must be one of the following: AB, BL, ID, IV.',
            'accounts.*.sc_no.required' => 'The SC number is required.',
            'accounts.*.meter_brand.string' => 'The meter brand must be a valid string.',
            'accounts.*.meter_brand.max' => 'The meter brand must not exceed 256 characters.',
            'accounts.*.meter_serial_no.required' => 'The meter serial number is required.',
            'accounts.*.date_connected.required' => 'The date connected is required.',
            'accounts.*.sequence_no.required' => 'The sequence number is required.',
            'accounts.*.meter_type.string' => 'The meter type must be a valid string.',
            'accounts.*.meter_type.max' => 'The meter type must not exceed 120 characters.',
            'accounts.*.meter_wire.string' => 'The meter wire must be a valid string.',
            'accounts.*.meter_wire.max' => 'The meter wire must not exceed 120 characters.',
            'accounts.*.meter_form.string' => 'The meter form must be a valid string.',
            'accounts.*.meter_form.max' => 'The meter form must not exceed 120 characters.',
            'accounts.*.meter_class.string' => 'The meter class must be a valid string.',
            'accounts.*.meter_class.max' => 'The meter class must not exceed 120 characters.',
            'accounts.*.lat_long.string' => 'The latitude and longitude must be a valid string.',
            'accounts.*.lat_long.max' => 'The latitude and longitude must not exceed 120 characters.',
            'accounts.*.isErcSealed.nullable' => 'The ERC seal field is optional.',
        ];
    }

}
