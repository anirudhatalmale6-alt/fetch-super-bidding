<?php

namespace App\Http\Controllers\Web\Company;

use App\Http\Controllers\Web\BaseController;
use App\Models\Admin\Owner;
use App\Models\Interstate\TruckingCompany;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class ProfileController extends BaseController
{
    /**
     * Show profile edit form
     */
    public function edit()
    {
        $owner = auth()->user()->owner;
        $company = $owner->truckingCompany;
        
        return view('company.profile.edit', compact('owner', 'company'));
    }

    /**
     * Update company profile
     */
    public function update(Request $request)
    {
        $owner = auth()->user()->owner;
        $company = $owner->truckingCompany;

        $validated = $request->validate([
            'company_name' => 'required|string|max:255',
            'owner_name' => 'required|string|max:255',
            'email' => 'required|email|unique:owners,email,' . $owner->id,
            'mobile' => 'required|string|max:20',
            'phone' => 'nullable|string|max:20',
            'address' => 'required|string|max:500',
            'city' => 'required|string|max:100',
            'postal_code' => 'required|string|max:20',
            'tax_number' => 'nullable|string|max:50',
            'bank_name' => 'nullable|string|max:100',
            'account_no' => 'nullable|string|max:50',
            'ifsc' => 'nullable|string|max:20',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            
            // Trucking company fields
            'hub_address' => 'nullable|string|max:500',
            'operating_states' => 'nullable|array',
            'fleet_size' => 'nullable|integer|min:1',
            'insurance_percentage' => 'nullable|numeric|min:0|max:100',
            'service_description' => 'nullable|string|max:1000',
        ]);

        // Handle logo upload
        if ($request->hasFile('logo')) {
            // Delete old logo if exists
            if ($owner->logo && Storage::exists($owner->logo)) {
                Storage::delete($owner->logo);
            }
            
            $logoPath = $request->file('logo')->store('company-logos', 'public');
            $validated['logo'] = $logoPath;
        }

        // Update owner
        $owner->update($validated);

        // Update user email
        $owner->user->update([
            'email' => $validated['email'],
            'name' => $validated['owner_name'],
        ]);

        // Update trucking company if exists
        if ($company) {
            $companyData = [
                'company_name' => $validated['company_name'],
                'email' => $validated['email'],
                'phone' => $validated['mobile'],
            ];

            if ($request->has('hub_address')) {
                $companyData['hub_address'] = $validated['hub_address'];
            }
            if ($request->has('fleet_size')) {
                $companyData['fleet_size'] = $validated['fleet_size'];
            }
            if ($request->has('insurance_percentage')) {
                $companyData['insurance_percentage'] = $validated['insurance_percentage'];
            }
            if ($request->has('service_description')) {
                $companyData['service_description'] = $validated['service_description'];
            }
            if ($request->has('operating_states')) {
                $companyData['operating_states'] = $validated['operating_states'];
            }

            $company->update($companyData);
        }

        return redirect()->route('company.profile.edit')
            ->with('success', 'Profile updated successfully.');
    }

    /**
     * Change password form
     */
    public function changePassword()
    {
        return view('company.profile.change-password');
    }

    /**
     * Update password
     */
    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = auth()->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return back()->withErrors(['current_password' => 'Current password is incorrect']);
        }

        $user->update([
            'password' => Hash::make($request->password),
        ]);

        return redirect()->route('company.profile.changePassword')
            ->with('success', 'Password changed successfully.');
    }

    /**
     * Show company documents
     */
    public function documents()
    {
        $owner = auth()->user()->owner;
        $documents = $owner->ownerDocument;
        
        return view('company.profile.documents', compact('owner', 'documents'));
    }

    /**
     * Upload document
     */
    public function uploadDocument(Request $request)
    {
        $request->validate([
            'document_id' => 'required|exists:owner_needed_documents,id',
            'document_file' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'expiry_date' => 'nullable|date|after:today',
            'identify_number' => 'nullable|string|max:100',
        ]);

        $owner = auth()->user()->owner;

        // Handle file upload
        $filePath = $request->file('document_file')->store('owner-documents', 'public');

        // Update or create document
        $owner->ownerDocument()->updateOrCreate(
            ['document_id' => $request->document_id],
            [
                'image' => $filePath,
                'expiry_date' => $request->expiry_date,
                'identify_number' => $request->identify_number,
                'document_status' => 0, // Pending approval
            ]
        );

        return redirect()->route('company.profile.documents')
            ->with('success', 'Document uploaded successfully. Awaiting approval.');
    }

    /**
     * Show company settings
     */
    public function settings()
    {
        $owner = auth()->user()->owner;
        $company = $owner->truckingCompany;
        
        return view('company.profile.settings', compact('owner', 'company'));
    }

    /**
     * Update settings
     */
    public function updateSettings(Request $request)
    {
        $owner = auth()->user()->owner;
        
        $validated = $request->validate([
            'notification_email' => 'boolean',
            'notification_sms' => 'boolean',
            'notification_push' => 'boolean',
            'auto_bid_enabled' => 'boolean',
            'auto_bid_max_amount' => 'nullable|numeric|min:0',
        ]);

        // Store settings in JSON column or separate table
        $owner->update([
            'settings' => json_encode($validated),
        ]);

        return redirect()->route('company.profile.settings')
            ->with('success', 'Settings updated successfully.');
    }
}
