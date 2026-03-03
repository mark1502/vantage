<?php

namespace Database\Seeders;

use App\Models\Contact;
use App\Models\ContactRole;
use App\Models\Entry;
use App\Models\Entrytype;
use App\Models\File;
use App\Models\Filetype;
use Illuminate\Database\Seeder;

class SampleDataSeeder extends Seeder
{
    private const FIRM_ID = 2;

    public function run(): void
    {
        if (Contact::where('firm_id', self::FIRM_ID)->exists()) {
            $this->command->info('Sample data already exists for firm '.self::FIRM_ID.'. Skipping.');

            return;
        }

        $this->copyFiletypes();
        $entrytypeMap = $this->copyEntrytypes();
        $contacts = $this->createContacts();
        $files = $this->createFiles($contacts);
        $this->createContactRoles($files, $contacts);
        $this->createEntries($files, $contacts, $entrytypeMap);

        $this->command->info('Sample data seeded successfully for firm '.self::FIRM_ID.'.');
    }

    private function copyFiletypes(): void
    {
        $existing = Filetype::where('firm_id', self::FIRM_ID)->pluck('name')->toArray();

        Filetype::where('firm_id', 1)->get()->each(function (Filetype $ft) use ($existing) {
            if (in_array($ft->name, $existing)) {
                return;
            }

            $replica = $ft->replicate();
            $replica->firm_id = self::FIRM_ID;
            $replica->save();
        });
    }

    /**
     * @return array<string, int> Map of "folder_id:name" => entrytype_id
     */
    private function copyEntrytypes(): array
    {
        $existing = Entrytype::where('firm_id', self::FIRM_ID)
            ->get()
            ->keyBy(fn (Entrytype $et) => $et->folder_id.':'.$et->name);

        $map = [];

        Entrytype::where('firm_id', 1)->get()->each(function (Entrytype $et) use ($existing, &$map) {
            $key = $et->folder_id.':'.$et->name;

            if ($existing->has($key)) {
                $map[$key] = $existing->get($key)->id;

                return;
            }

            $replica = $et->replicate();
            $replica->firm_id = self::FIRM_ID;
            $replica->save();
            $map[$key] = $replica->id;
        });

        return $map;
    }

    /**
     * @return array<string, Contact>
     */
    private function createContacts(): array
    {
        $contacts = [];

        // Firm members
        $contacts['adams'] = Contact::create([
            'firm_id' => self::FIRM_ID,
            'title' => 'Mr.',
            'first_name' => 'John',
            'last_name' => 'Adams',
            'display_name' => 'Adams, John',
            'display_last_first' => 'Adams, John',
            'is_firm_member' => true,
            'account_status' => 'A',
            'member_initials' => 'JA',
            'firm_role' => 'Attorney',
        ]);

        $contacts['chen'] = Contact::create([
            'firm_id' => self::FIRM_ID,
            'title' => 'Ms.',
            'first_name' => 'Sarah',
            'last_name' => 'Chen',
            'display_name' => 'Chen, Sarah',
            'display_last_first' => 'Chen, Sarah',
            'is_firm_member' => true,
            'account_status' => 'A',
            'member_initials' => 'SC',
            'firm_role' => 'Paralegal',
        ]);

        // External contacts
        $contacts['garcia'] = Contact::create([
            'firm_id' => self::FIRM_ID,
            'title' => 'Ms.',
            'first_name' => 'Maria',
            'last_name' => 'Garcia',
            'display_name' => 'Garcia, Maria',
            'display_last_first' => 'Garcia, Maria',
            'is_firm_member' => false,
            'account_status' => 'N',
        ]);

        $contacts['thompson'] = Contact::create([
            'firm_id' => self::FIRM_ID,
            'title' => 'Mr.',
            'first_name' => 'Robert',
            'last_name' => 'Thompson',
            'display_name' => 'Thompson, Robert',
            'display_last_first' => 'Thompson, Robert',
            'is_firm_member' => false,
            'account_status' => 'N',
        ]);

        $contacts['whitfield'] = Contact::create([
            'firm_id' => self::FIRM_ID,
            'title' => 'Ms.',
            'first_name' => 'Eleanor',
            'last_name' => 'Whitfield',
            'display_name' => 'Whitfield, Eleanor',
            'display_last_first' => 'Whitfield, Eleanor',
            'is_firm_member' => false,
            'account_status' => 'N',
        ]);

        $contacts['morrison'] = Contact::create([
            'firm_id' => self::FIRM_ID,
            'title' => 'Mr.',
            'first_name' => 'James',
            'last_name' => 'Morrison',
            'display_name' => 'Morrison, James',
            'display_last_first' => 'Morrison, James',
            'is_firm_member' => false,
            'account_status' => 'N',
        ]);

        $contacts['park'] = Contact::create([
            'firm_id' => self::FIRM_ID,
            'title' => 'Dr.',
            'first_name' => 'Lisa',
            'last_name' => 'Park',
            'display_name' => 'Park, Dr. Lisa',
            'display_last_first' => 'Park, Lisa',
            'is_firm_member' => false,
            'account_status' => 'N',
        ]);

        $contacts['westside'] = Contact::create([
            'firm_id' => self::FIRM_ID,
            'title' => 'Co.',
            'company' => 'Westside Medical Center',
            'display_name' => 'Westside Medical Center',
            'display_last_first' => 'Westside Medical Center',
            'is_firm_member' => false,
            'account_status' => 'N',
        ]);

        $contacts['apex'] = Contact::create([
            'firm_id' => self::FIRM_ID,
            'title' => 'Co.',
            'company' => 'Apex Insurance Co',
            'display_name' => 'Apex Insurance Co',
            'display_last_first' => 'Apex Insurance Co',
            'is_firm_member' => false,
            'account_status' => 'N',
        ]);

        $contacts['nguyen'] = Contact::create([
            'firm_id' => self::FIRM_ID,
            'title' => 'Mr.',
            'first_name' => 'David',
            'last_name' => 'Nguyen',
            'display_name' => 'Nguyen, David',
            'display_last_first' => 'Nguyen, David',
            'is_firm_member' => false,
            'account_status' => 'N',
        ]);

        $contacts['brightpath'] = Contact::create([
            'firm_id' => self::FIRM_ID,
            'title' => 'Co.',
            'company' => 'BrightPath Solutions LLC',
            'display_name' => 'BrightPath Solutions LLC',
            'display_last_first' => 'BrightPath Solutions LLC',
            'is_firm_member' => false,
            'account_status' => 'N',
        ]);

        return $contacts;
    }

    /**
     * @return array<string, File>
     */
    private function createFiles(array $contacts): array
    {
        $piType = Filetype::where('firm_id', self::FIRM_ID)->where('name', 'Personal Injury')->first();
        $contractType = Filetype::where('firm_id', self::FIRM_ID)->where('name', 'Contract')->first();
        $estateType = Filetype::where('firm_id', self::FIRM_ID)->where('name', 'Estate Planning')->first();

        $files = [];

        $files['pi'] = File::create([
            'firm_id' => self::FIRM_ID,
            'name' => 'Garcia v. Morrison',
            'filetype_id' => $piType->id,
            'date_opened' => '2025-06-15',
            'date_sol' => '2027-06-15',
            'summary' => 'Auto accident personal injury case. Client rear-ended at intersection.',
        ]);

        $files['contract'] = File::create([
            'firm_id' => self::FIRM_ID,
            'name' => 'Thompson v. BrightPath Solutions',
            'filetype_id' => $contractType->id,
            'date_opened' => '2025-09-01',
            'date_sol' => '2027-09-01',
            'summary' => 'Breach of contract dispute regarding software development services.',
        ]);

        $files['estate'] = File::create([
            'firm_id' => self::FIRM_ID,
            'name' => 'Whitfield Estate Plan',
            'filetype_id' => $estateType->id,
            'date_opened' => '2025-11-01',
            'summary' => 'Comprehensive estate planning including will, trusts, and powers of attorney.',
        ]);

        return $files;
    }

    private function createContactRoles(array $files, array $contacts): void
    {
        // --- Garcia v. Morrison (PI) ---
        ContactRole::create(['file_id' => $files['pi']->id, 'contact_id' => $contacts['garcia']->id, 'role' => 'client', 'is_file_client' => true]);
        ContactRole::create(['file_id' => $files['pi']->id, 'contact_id' => $contacts['adams']->id, 'role' => 'attorney_for_client', 'is_file_attorney' => true]);
        ContactRole::create(['file_id' => $files['pi']->id, 'contact_id' => $contacts['morrison']->id, 'role' => 'opposing_party']);
        ContactRole::create(['file_id' => $files['pi']->id, 'contact_id' => $contacts['park']->id, 'role' => 'medical_provider']);
        ContactRole::create(['file_id' => $files['pi']->id, 'contact_id' => $contacts['westside']->id, 'role' => 'medical_facility']);
        ContactRole::create(['file_id' => $files['pi']->id, 'contact_id' => $contacts['apex']->id, 'role' => 'insurance_adjuster']);

        // --- Thompson v. BrightPath (Contract) ---
        ContactRole::create(['file_id' => $files['contract']->id, 'contact_id' => $contacts['thompson']->id, 'role' => 'client', 'is_file_client' => true]);
        ContactRole::create(['file_id' => $files['contract']->id, 'contact_id' => $contacts['adams']->id, 'role' => 'attorney_for_client', 'is_file_attorney' => true]);
        ContactRole::create(['file_id' => $files['contract']->id, 'contact_id' => $contacts['brightpath']->id, 'role' => 'opposing_party']);
        ContactRole::create(['file_id' => $files['contract']->id, 'contact_id' => $contacts['nguyen']->id, 'role' => 'opposing_counsel']);

        // --- Whitfield Estate Plan ---
        ContactRole::create(['file_id' => $files['estate']->id, 'contact_id' => $contacts['whitfield']->id, 'role' => 'client', 'is_file_client' => true]);
        ContactRole::create(['file_id' => $files['estate']->id, 'contact_id' => $contacts['adams']->id, 'role' => 'attorney_for_client', 'is_file_attorney' => true]);
    }

    private function createEntries(array $files, array $contacts, array $entrytypeMap): void
    {
        $this->createPiEntries($files['pi'], $contacts, $entrytypeMap);
        $this->createContractEntries($files['contract'], $contacts, $entrytypeMap);
        $this->createEstateEntries($files['estate'], $contacts, $entrytypeMap);
    }

    private function et(array $map, int $folderId, string $name): int
    {
        return $map[$folderId.':'.$name];
    }

    private function entry(File $file, int $folderId, int $entrytypeId, int $fromId, ?int $toId, string $date, string $note, ?float $amount = null): void
    {
        Entry::create([
            'firm_id' => self::FIRM_ID,
            'file_id' => $file->id,
            'folder_id' => $folderId,
            'entrytype_id' => $entrytypeId,
            'from_contact_id' => $fromId,
            'to_contact_id' => $toId,
            'date1' => $date,
            'note' => $note,
            'amount' => $amount,
        ]);
    }

    private function createPiEntries(File $file, array $contacts, array $m): void
    {
        $adams = $contacts['adams']->id;
        $chen = $contacts['chen']->id;
        $garcia = $contacts['garcia']->id;
        $morrison = $contacts['morrison']->id;
        $park = $contacts['park']->id;
        $westside = $contacts['westside']->id;
        $apex = $contacts['apex']->id;

        // Correspondence (folder 1)
        $this->entry($file, 1, $this->et($m, 1, 'Letter'), $adams, $garcia, '2025-06-16', 'Retainer agreement and engagement letter sent to client.');
        $this->entry($file, 1, $this->et($m, 1, 'Letter'), $adams, $apex, '2025-06-20', 'Letter of representation and initial claim notice sent to insurance company.');
        $this->entry($file, 1, $this->et($m, 1, 'Letter'), $apex, $adams, '2025-07-15', 'Claim denial letter received. Insurance company disputes liability.');
        $this->entry($file, 1, $this->et($m, 1, 'Email'), $adams, $morrison, '2025-09-10', 'Email regarding initial disclosures and discovery schedule.');
        $this->entry($file, 1, $this->et($m, 1, 'Email'), $morrison, $adams, '2025-12-01', 'Email from opposing counsel proposing settlement discussion.');
        $this->entry($file, 1, $this->et($m, 1, 'Letter'), $adams, $park, '2025-07-01', 'Request for medical records and treatment summary.');

        // Pleadings (folder 2)
        $this->entry($file, 2, $this->et($m, 2, 'Complaint'), $adams, null, '2025-08-15', 'Complaint filed in Superior Court. Negligence and personal injury claims.');
        $this->entry($file, 2, $this->et($m, 2, 'Answer'), $morrison, null, '2025-09-20', 'Answer received from defendant denying all allegations.');
        $this->entry($file, 2, $this->et($m, 2, 'Motion to Compel'), $adams, null, '2025-11-15', 'Motion to Compel discovery responses filed. Defendant failed to respond to interrogatories.');
        $this->entry($file, 2, $this->et($m, 2, 'Response to Motion to Compel'), $morrison, null, '2025-12-05', 'Response to Motion to Compel received from opposing counsel.');

        // Discovery (folder 3)
        $this->entry($file, 3, $this->et($m, 3, 'Interrogatories'), $adams, $morrison, '2025-09-25', 'First set of interrogatories served on defendant.');
        $this->entry($file, 3, $this->et($m, 3, 'Answers to Interrogatores'), $morrison, $adams, '2025-11-20', 'Answers to interrogatories received from defendant.');
        $this->entry($file, 3, $this->et($m, 3, 'Request for Production'), $adams, $morrison, '2025-09-25', 'Request for production of documents served on defendant.');
        $this->entry($file, 3, $this->et($m, 3, 'Response to Request for Production'), $morrison, $adams, '2025-11-20', 'Response to request for production received. Some documents withheld on privilege grounds.');

        // Memos (folder 5)
        $this->entry($file, 5, $this->et($m, 5, 'Memo'), $adams, null, '2025-06-18', 'Initial case review. Strong liability case — clear rear-end collision. Estimated damages $75,000-$125,000.');
        $this->entry($file, 5, $this->et($m, 5, 'Memo'), $adams, null, '2025-12-10', 'Settlement analysis. Insurance policy limits $100,000. Recommend counter-offer at $85,000.');

        // Events (folder 6)
        $this->entry($file, 6, $this->et($m, 6, 'Meeting'), $adams, $garcia, '2025-06-15', 'Initial client meeting. Discussed accident details, injuries, and case strategy.');
        $this->entry($file, 6, $this->et($m, 6, 'Deposition'), $adams, null, '2026-01-20', 'Deposition of plaintiff Maria Garcia scheduled at our office.');
        $this->entry($file, 6, $this->et($m, 6, 'Hearing'), $adams, null, '2025-12-15', 'Hearing on Motion to Compel. Court granted motion — defendant ordered to respond within 14 days.');

        // Phone Messages (folder 8)
        $this->entry($file, 8, $this->et($m, 8, 'Telephoned'), $apex, $chen, '2025-07-20', 'Insurance adjuster called to discuss claim. Requested additional documentation of injuries.');
        $this->entry($file, 8, $this->et($m, 8, 'Telephoned'), $garcia, $chen, '2025-08-05', 'Client called asking about status of treatment authorization from insurance.');

        // Medical Records (folder 9)
        $this->entry($file, 9, $this->et($m, 9, 'Hospital Records'), $westside, $adams, '2025-07-10', 'Emergency room records received. Diagnosis: cervical strain, lumbar sprain, contusions.');
        $this->entry($file, 9, $this->et($m, 9, 'Treatment Records'), $park, $adams, '2025-08-20', 'Physical therapy treatment records received. 12 sessions completed.');

        // Medical Bills (folder 10)
        $this->entry($file, 10, $this->et($m, 10, 'Medical Bill'), $westside, null, '2025-07-10', 'Westside Medical Center ER visit.', 3200.00);
        $this->entry($file, 10, $this->et($m, 10, 'Medical Bill'), $park, null, '2025-09-15', 'Physical therapy — 12 sessions.', 1850.00);

        // Case Costs (folder 11)
        $this->entry($file, 11, $this->et($m, 11, 'Case Cost'), $adams, null, '2025-08-15', 'Court filing fee for complaint.', 400.00);
        $this->entry($file, 11, $this->et($m, 11, 'Case Cost'), $adams, null, '2025-08-18', 'Process server fee for service of summons and complaint.', 75.00);
    }

    private function createContractEntries(File $file, array $contacts, array $m): void
    {
        $adams = $contacts['adams']->id;
        $chen = $contacts['chen']->id;
        $thompson = $contacts['thompson']->id;
        $nguyen = $contacts['nguyen']->id;
        $brightpath = $contacts['brightpath']->id;

        // Correspondence (folder 1)
        $this->entry($file, 1, $this->et($m, 1, 'Letter'), $adams, $brightpath, '2025-09-05', 'Demand letter regarding breach of software development contract. Seeking $45,000 in damages.');
        $this->entry($file, 1, $this->et($m, 1, 'Letter'), $nguyen, $adams, '2025-09-20', 'Response from opposing counsel denying breach and asserting performance defense.');
        $this->entry($file, 1, $this->et($m, 1, 'Email'), $adams, $thompson, '2025-10-15', 'Case update email to client. Discussed discovery timeline and mediation option.');
        $this->entry($file, 1, $this->et($m, 1, 'Letter'), $adams, $nguyen, '2025-11-10', 'Letter proposing mediation before proceeding to summary judgment.');
        $this->entry($file, 1, $this->et($m, 1, 'Email'), $nguyen, $adams, '2026-01-05', 'Email from opposing counsel regarding document production schedule.');

        // Pleadings (folder 2)
        $this->entry($file, 2, $this->et($m, 2, 'Complaint'), $adams, null, '2025-10-01', 'Complaint filed for breach of contract. Three counts: breach, unjust enrichment, damages.');
        $this->entry($file, 2, $this->et($m, 2, 'Answer'), $nguyen, null, '2025-10-30', 'Answer and counterclaim received. Defendant alleges client failed to provide adequate specifications.');
        $this->entry($file, 2, $this->et($m, 2, 'Motion for Summary Judgment'), $adams, null, '2026-02-01', 'Motion for summary judgment filed on breach of contract count.');

        // Discovery (folder 3)
        $this->entry($file, 3, $this->et($m, 3, 'Interrogatories'), $adams, $nguyen, '2025-11-01', 'First set of interrogatories served on defendant.');
        $this->entry($file, 3, $this->et($m, 3, 'Answers to Interrogatores'), $nguyen, $adams, '2025-12-15', 'Answers to interrogatories received from defendant.');
        $this->entry($file, 3, $this->et($m, 3, 'Request for Production'), $adams, $nguyen, '2025-11-01', 'Request for production of contract documents, emails, and project files.');
        $this->entry($file, 3, $this->et($m, 3, 'Response to Request for Production'), $nguyen, $adams, '2025-12-15', 'Response to request for production received. 450 pages of documents produced.');

        // Memos (folder 5)
        $this->entry($file, 5, $this->et($m, 5, 'Memo'), $adams, null, '2025-09-03', 'Case strategy memo. Contract terms strongly favor our client. Key issue is scope of deliverables.');
        $this->entry($file, 5, $this->et($m, 5, 'Memo'), $chen, null, '2025-11-20', 'Contract analysis memo. Identified 4 specific deliverables defendant failed to complete per Section 3.2.');

        // Events (folder 6)
        $this->entry($file, 6, $this->et($m, 6, 'Meeting'), $adams, $thompson, '2025-09-01', 'Initial client meeting. Reviewed contract terms and discussed litigation strategy.');
        $this->entry($file, 6, $this->et($m, 6, 'Meeting'), $adams, $nguyen, '2026-01-15', 'Mediation session. No resolution reached — parties $15,000 apart.');
        $this->entry($file, 6, $this->et($m, 6, 'Hearing'), $adams, null, '2026-02-20', 'Hearing on motion for summary judgment scheduled.');

        // Phone Messages (folder 8)
        $this->entry($file, 8, $this->et($m, 8, 'Telephoned'), $thompson, $chen, '2025-10-20', 'Client called asking about timeline for case resolution.');
        $this->entry($file, 8, $this->et($m, 8, 'Returning Your Call'), $nguyen, $adams, '2025-11-05', 'Opposing counsel returning call regarding discovery extension request.');

        // To-Do (folder 7)
        $this->entry($file, 7, $this->et($m, 7, 'To-Do'), $chen, null, '2025-09-10', 'Review contract terms and identify all unfulfilled deliverables.');
        $this->entry($file, 7, $this->et($m, 7, 'To-Do'), $adams, null, '2026-01-10', 'Prepare mediation brief with damages calculation and supporting exhibits.');
    }

    private function createEstateEntries(File $file, array $contacts, array $m): void
    {
        $adams = $contacts['adams']->id;
        $chen = $contacts['chen']->id;
        $whitfield = $contacts['whitfield']->id;

        // Correspondence (folder 1)
        $this->entry($file, 1, $this->et($m, 1, 'Letter'), $adams, $whitfield, '2025-11-02', 'Engagement letter and estate planning questionnaire sent to client.');
        $this->entry($file, 1, $this->et($m, 1, 'Letter'), $adams, $whitfield, '2025-12-10', 'Letter to client enclosing draft estate plan documents for review.');
        $this->entry($file, 1, $this->et($m, 1, 'Email'), $whitfield, $adams, '2025-12-01', 'Client provided comprehensive asset list including real property, investments, and insurance.');

        // Documents (folder 4)
        $this->entry($file, 4, $this->et($m, 4, 'Insurance Records'), $whitfield, $adams, '2025-11-15', 'Life insurance policy received. $500,000 whole life policy, beneficiary is spouse.');
        $this->entry($file, 4, $this->et($m, 4, 'Insurance Records'), $whitfield, $adams, '2025-11-15', 'Property deeds received for primary residence and vacation property.');

        // Memos (folder 5)
        $this->entry($file, 5, $this->et($m, 5, 'Memo'), $adams, null, '2025-11-10', 'Estate plan review memo. Client has approximately $2.1M in assets. Recommend revocable living trust.');
        $this->entry($file, 5, $this->et($m, 5, 'Memo'), $adams, null, '2025-12-05', 'Tax implications memo. Estate below federal exemption threshold. State estate tax considerations noted.');

        // Events (folder 6)
        $this->entry($file, 6, $this->et($m, 6, 'Meeting'), $adams, $whitfield, '2025-11-01', 'Initial consultation. Discussed estate planning goals, family situation, and asset overview.');
        $this->entry($file, 6, $this->et($m, 6, 'Meeting'), $adams, $whitfield, '2025-12-15', 'Follow-up meeting to review draft documents. Client requested changes to trustee designations.');

        // Phone Messages (folder 8)
        $this->entry($file, 8, $this->et($m, 8, 'Telephoned'), $whitfield, $chen, '2025-12-20', 'Client called regarding beneficiary changes on life insurance and retirement accounts.');
        $this->entry($file, 8, $this->et($m, 8, 'Please Call'), $chen, $whitfield, '2025-12-22', 'Called financial advisor to coordinate beneficiary designation updates.');

        // To-Do (folder 7)
        $this->entry($file, 7, $this->et($m, 7, 'To-Do'), $adams, null, '2025-11-05', 'Draft last will and testament incorporating client instructions.');
        $this->entry($file, 7, $this->et($m, 7, 'To-Do'), $adams, null, '2025-11-05', 'Prepare durable power of attorney and healthcare directive documents.');
    }
}
