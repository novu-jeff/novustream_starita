<?php

namespace Database\Seeders;

use App\Models\TicketsCategory;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class TicketsCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $data = [
            [
                'category' => 'Technical Issues',
                'name' => 'Website/App Errors'
            ],
            [
                'category' => 'Technical Issues',
                'name' => 'Software Bugs'
            ],
            [
                'category' => 'Technical Issues',
                'name' => 'Performance Issues'
            ],
            [
                'category' => 'Technical Issues',
                'name' => 'Login/Authentication Problems'
            ],
            [
                'category' => 'Technical Issues',
                'name' => 'Connectivity Issues'
            ],
            [
                'category' => 'Technical Issues',
                'name' => 'Data Loss/Corruption'
            ],
            [
                'category' => 'Account Management',
                'name' => 'Password Reset'
            ],
            [
                'category' => 'Account Management',
                'name' => 'Account Lockout'
            ],
            [
                'category' => 'Account Management',
                'name' => 'Profile Update'
            ],
            [
                'category' => 'Account Management',
                'name' => 'Subscription/Billing Queries'
            ],
            [
                'category' => 'Account Management',
                'name' => 'Account Deactivation/Deletion'
            ],
            [
                'category' => 'Billing and Payments',
                'name' => 'Payment Failure'
            ],
            [
                'category' => 'Billing and Payments',
                'name' => 'Refund Requests'
            ],
            [
                'category' => 'Billing and Payments',
                'name' => 'Invoice Requests'
            ],
            [
                'category' => 'Billing and Payments',
                'name' => 'Overcharged'
            ],
            [
                'category' => 'Billing and Payments',
                'name' => 'Billing Discrepancies'
            ],
            [
                'category' => 'Billing and Payments',
                'name' => 'Subscription Issues'
            ],
            [
                'category' => 'Product or Service Inquiry',
                'name' => 'Product Information'
            ],
            [
                'category' => 'Product or Service Inquiry',
                'name' => 'Service Explanation'
            ],
            [
                'category' => 'Product or Service Inquiry',
                'name' => 'Pricing Queries'
            ],
            [
                'category' => 'Product or Service Inquiry',
                'name' => 'Availability Inquiries'
            ],
            [
                'category' => 'Product or Service Inquiry',
                'name' => 'Trial/Free Sample Requests'
            ],
            [
                'category' => 'Feature Requests',
                'name' => 'New Feature Suggestions'
            ],
            [
                'category' => 'Feature Requests',
                'name' => 'Improvement Suggestions'
            ],
            [
                'category' => 'Feature Requests',
                'name' => 'Feedback on Existing Features'
            ],
            [
                'category' => 'Customer Support',
                'name' => 'Order Status'
            ],
            [
                'category' => 'Customer Support',
                'name' => 'Shipping/Delivery Issues'
            ],
            [
                'category' => 'Customer Support',
                'name' => 'Product Return/Exchange'
            ],
            [
                'category' => 'Customer Support',
                'name' => 'Warranty Claims'
            ],
            [
                'category' => 'Customer Support',
                'name' => 'Complaint Escalation'
            ],
            [
                'category' => 'Security Concerns',
                'name' => 'Suspicious Activity'
            ],
            [
                'category' => 'Security Concerns',
                'name' => 'Phishing Attempts'
            ],
            [
                'category' => 'Security Concerns',
                'name' => 'Data Breach Notification'
            ],
            [
                'category' => 'Security Concerns',
                'name' => 'Unauthorized Access'
            ],
            [
                'category' => 'Security Concerns',
                'name' => 'Privacy Issues'
            ],
            [
                'category' => 'Feedback/Complaints',
                'name' => 'Service Feedback'
            ],
            [
                'category' => 'Feedback/Complaints',
                'name' => 'Product Complaints'
            ],
            [
                'category' => 'Feedback/Complaints',
                'name' => 'Staff Complaints'
            ],
            [
                'category' => 'Feedback/Complaints',
                'name' => 'General Suggestions'
            ],
            [
                'category' => 'Feedback/Complaints',
                'name' => 'Satisfaction Surveys'
            ],
            [
                'category' => 'Legal Issues',
                'name' => 'Terms of Service'
            ],
            [
                'category' => 'Legal Issues',
                'name' => 'Privacy Policy'
            ],
            [
                'category' => 'Legal Issues',
                'name' => 'Intellectual Property'
            ],
            [
                'category' => 'Legal Issues',
                'name' => 'Legal Inquiries'
            ],
            [
                'category' => 'Legal Issues',
                'name' => 'Compliance Issues'
            ],
            [
                'category' => 'General Inquiry',
                'name' => 'Information Requests'
            ],
            [
                'category' => 'General Inquiry',
                'name' => 'Contact Information Update'
            ],
            [
                'category' => 'General Inquiry',
                'name' => 'Miscellaneous Queries'
            ],
        ];

        $records = TicketsCategory::whereIn('category', array_column($data, 'category'))
            ->whereIn('name', array_column($data, 'name'));
        
        if($records->exists()) {
            foreach($data as $key => $value) {
                TicketsCategory::where('id', ($key + 1))
                    ->update($value);
            }
        } else {
            TicketsCategory::insert($data);
        }

    }
}