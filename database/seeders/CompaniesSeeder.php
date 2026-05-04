<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CompaniesSeeder extends Seeder
{
    public function run(): void
    {
        // Map municipality codes to their IDs
        $municipalities = DB::table('municipalities')->pluck('id', 'code');

        $companies = [

            // ---------------------------------------------
            // City of Tshwane (COT)
            // ---------------------------------------------
            ['name' => '1 Life Direct Insurance Ltd', 'municipality_id' => $municipalities['COT'] ?? null],
            ['name' => 'Abacus Lending - Cellphones', 'municipality_id' => $municipalities['COT'] ?? null],
            ['name' => 'Absa Vehicle & Asset Finance', 'municipality_id' => $municipalities['COT'] ?? null],
            ['name' => 'ADCOCK INGRAM EMPLOYEE FUND', 'municipality_id' => $municipalities['COT'] ?? null],
            ['name' => 'Alexander Forbes Group', 'municipality_id' => $municipalities['COT'] ?? null],
            ['name' => 'Alphasure Underwriting Managers', 'municipality_id' => $municipalities['COT'] ?? null],
            ['name' => 'Assupol Life Lmt', 'municipality_id' => $municipalities['COT'] ?? null],
            ['name' => 'AVBOB Mutual Assur Society', 'municipality_id' => $municipalities['COT'] ?? null],
            ['name' => 'Bayport Financial Services', 'municipality_id' => $municipalities['COT'] ?? null],
            ['name' => 'Bayport Life Plus - Traffic Insurance', 'municipality_id' => $municipalities['COT'] ?? null],
            ['name' => 'Capital Legacy Solutions Pty Ltd', 'municipality_id' => $municipalities['COT'] ?? null],
            ['name' => 'Cede Capital Pty Ltd - Insurance', 'municipality_id' => $municipalities['COT'] ?? null],
            ['name' => 'Cede Capital Pty Ltd - Loan', 'municipality_id' => $municipalities['COT'] ?? null],
            ['name' => 'Channel Life', 'municipality_id' => $municipalities['COT'] ?? null],
            ['name' => 'Clientele Life Assurance Company Ltd', 'municipality_id' => $municipalities['COT'] ?? null],
            ['name' => 'Credit Guarantee Insurance Co', 'municipality_id' => $municipalities['COT'] ?? null],
            ['name' => 'Data Wallet Pty Ltd', 'municipality_id' => $municipalities['COT'] ?? null],
            ['name' => 'Datacapital Technologies & ISP', 'municipality_id' => $municipalities['COT'] ?? null],
            ['name' => 'Discovery Life Ltd - Funeral', 'municipality_id' => $municipalities['COT'] ?? null],
            ['name' => 'Emerald Life CC', 'municipality_id' => $municipalities['COT'] ?? null],
            ['name' => 'Empower Financial Pty Ltd', 'municipality_id' => $municipalities['COT'] ?? null],
            ['name' => 'Empower Financial Pty Ltd - Salary Advance', 'municipality_id' => $municipalities['COT'] ?? null],
            ['name' => 'FFS Finance', 'municipality_id' => $municipalities['COT'] ?? null],
            ['name' => 'First National Bank', 'municipality_id' => $municipalities['COT'] ?? null],
            ['name' => 'First National Bank - Home loan', 'municipality_id' => $municipalities['COT'] ?? null],
            ['name' => 'FNB - Home loan', 'municipality_id' => $municipalities['COT'] ?? null],
            ['name' => 'Fundi Capital Pty Ltd', 'municipality_id' => $municipalities['COT'] ?? null],
            ['name' => 'Government Empl Personal Finance Pty Ltd', 'municipality_id' => $municipalities['COT'] ?? null],
            ['name' => 'Government Employees Fund', 'municipality_id' => $municipalities['COT'] ?? null],
            ['name' => 'Legal Wise', 'municipality_id' => $municipalities['COT'] ?? null],
            ['name' => 'Liberty Corporate', 'municipality_id' => $municipalities['COT'] ?? null],
            ['name' => 'Lion Life - Lion of Africa', 'municipality_id' => $municipalities['COT'] ?? null],
            ['name' => 'M&H Bridging Finance Pty Ltd', 'municipality_id' => $municipalities['COT'] ?? null],
            ['name' => 'Metropolitan Group & Life Ltd', 'municipality_id' => $municipalities['COT'] ?? null],
            ['name' => 'MMK Financial Solutions', 'municipality_id' => $municipalities['COT'] ?? null],
            ['name' => 'Old Mutaul Group', 'municipality_id' => $municipalities['COT'] ?? null],
            ['name' => 'Old Mutual Group', 'municipality_id' => $municipalities['COT'] ?? null],
            ['name' => 'Old Mutual Life', 'municipality_id' => $municipalities['COT'] ?? null],
            ['name' => 'Rand Mutual Assurance Co Ltd', 'municipality_id' => $municipalities['COT'] ?? null],
            ['name' => 'Retail Financial Services', 'municipality_id' => $municipalities['COT'] ?? null],
            ['name' => 'Saambou', 'municipality_id' => $municipalities['COT'] ?? null],
            ['name' => 'Sanlam Sky Solutions', 'municipality_id' => $municipalities['COT'] ?? null],
            ['name' => 'Scorpion - Legal Protection', 'municipality_id' => $municipalities['COT'] ?? null],
            ['name' => 'Service Staff Provident Fund', 'municipality_id' => $municipalities['COT'] ?? null],
            ['name' => 'Stangen Limited', 'municipality_id' => $municipalities['COT'] ?? null],
            ['name' => 'Transnet', 'municipality_id' => $municipalities['COT'] ?? null],
            ['name' => 'We Buy Cars', 'municipality_id' => $municipalities['COT'] ?? null],
            ['name' => 'Workmens Compensation Fund', 'municipality_id' => $municipalities['COT'] ?? null],
            ['name' => 'letsatsi', 'municipality_id' => $municipalities['COT'] ?? null],

            // ---------------------------------------------
            // Ekurhuleni (EKU)
            // ---------------------------------------------
            ['name' => '1 Life Direct Insurance Ltd', 'municipality_id' => $municipalities['EKU'] ?? null],
            ['name' => '21St Century Funeral Brokers Pty Ltd', 'municipality_id' => $municipalities['EKU'] ?? null],
            ['name' => '32 Sign Club', 'municipality_id' => $municipalities['EKU'] ?? null],
            ['name' => 'Abacus Lending - Cellphones', 'municipality_id' => $municipalities['EKU'] ?? null],
            ['name' => 'Abacus Lending - Wellness Loans', 'municipality_id' => $municipalities['EKU'] ?? null],
            ['name' => 'Abacus Lending - Wellness Policies', 'municipality_id' => $municipalities['EKU'] ?? null],
            ['name' => 'Absa Vehicle & Asset Finance', 'municipality_id' => $municipalities['EKU'] ?? null],
            ['name' => 'ADCOCK INGRAM EMPLOYEE FUND', 'municipality_id' => $municipalities['EKU'] ?? null],
            ['name' => 'Aganang Basebetsi Club', 'municipality_id' => $municipalities['EKU'] ?? null],
            ['name' => 'AJMS Group', 'municipality_id' => $municipalities['EKU'] ?? null],
            ['name' => 'Alexander Forbes Group', 'municipality_id' => $municipalities['EKU'] ?? null],
            ['name' => 'Alphasure Underwriting Managers', 'municipality_id' => $municipalities['EKU'] ?? null],
            ['name' => 'Allowance', 'municipality_id' => $municipalities['EKU'] ?? null],
            ['name' => 'Amazing Shift Club', 'municipality_id' => $municipalities['EKU'] ?? null],
            ['name' => 'ASAP Training and Consulting', 'municipality_id' => $municipalities['EKU'] ?? null],
            ['name' => 'Assupol Life Lmt', 'municipality_id' => $municipalities['EKU'] ?? null],
            ['name' => 'AVBOB Mutual Assur Society', 'municipality_id' => $municipalities['EKU'] ?? null],
            ['name' => 'B3 Insurance Brokers CC', 'municipality_id' => $municipalities['EKU'] ?? null],
            ['name' => 'Basebenzi Bahlangene Burial Soc', 'municipality_id' => $municipalities['EKU'] ?? null],
            ['name' => 'Bathobatsho Club', 'municipality_id' => $municipalities['EKU'] ?? null],
            ['name' => 'Bayport Financial Services', 'municipality_id' => $municipalities['EKU'] ?? null],
            ['name' => 'Boksburg High School', 'municipality_id' => $municipalities['EKU'] ?? null],
            ['name' => 'Boston City Campus Pty Ltd', 'municipality_id' => $municipalities['EKU'] ?? null],
            ['name' => 'Brakpan Energy MENS Club', 'municipality_id' => $municipalities['EKU'] ?? null],
            ['name' => 'Buffalo Insurance Brokers', 'municipality_id' => $municipalities['EKU'] ?? null],
            ['name' => 'Capital Legacy Solutions Pty Ltd', 'municipality_id' => $municipalities['EKU'] ?? null],
            ['name' => 'Capitec Loan', 'municipality_id' => $municipalities['EKU'] ?? null],
            ['name' => 'Channel Life', 'municipality_id' => $municipalities['EKU'] ?? null],
            ['name' => 'Clientele Life Assurance Company Ltd', 'municipality_id' => $municipalities['EKU'] ?? null],
            ['name' => 'Credit Guarantee Insurance Co', 'municipality_id' => $municipalities['EKU'] ?? null],
            ['name' => 'Credit Gateway - Cellphone', 'municipality_id' => $municipalities['EKU'] ?? null],
            ['name' => 'Da Champ Security', 'municipality_id' => $municipalities['EKU'] ?? null],
            ['name' => 'Data Wallet Pty Ltd', 'municipality_id' => $municipalities['EKU'] ?? null],
            ['name' => 'Datacapital Technologies & ISP', 'municipality_id' => $municipalities['EKU'] ?? null],
            ['name' => 'Delta Kempton Group Stokvel', 'municipality_id' => $municipalities['EKU'] ?? null],
            ['name' => 'Development Bank of SA', 'municipality_id' => $municipalities['EKU'] ?? null],
            ['name' => 'Discovery Life Ltd - Funeral', 'municipality_id' => $municipalities['EKU'] ?? null],
            ['name' => 'Duraform (Pty) Ltd - Training', 'municipality_id' => $municipalities['EKU'] ?? null],
            ['name' => 'Ebony Burial Society', 'municipality_id' => $municipalities['EKU'] ?? null],
            ['name' => 'Emerald Life CC', 'municipality_id' => $municipalities['EKU'] ?? null],
            ['name' => 'Empower Financial Pty Ltd', 'municipality_id' => $municipalities['EKU'] ?? null],
            ['name' => 'Empower Financial Pty Ltd - Salary Advance', 'municipality_id' => $municipalities['EKU'] ?? null],
            ['name' => 'FFS Finance', 'municipality_id' => $municipalities['EKU'] ?? null],
            ['name' => 'First National Bank', 'municipality_id' => $municipalities['EKU'] ?? null],
            ['name' => 'First National Bank - Home loan', 'municipality_id' => $municipalities['EKU'] ?? null],
            ['name' => 'FNB - Home loan', 'municipality_id' => $municipalities['EKU'] ?? null],
            ['name' => 'Fundi Capital Pty Ltd', 'municipality_id' => $municipalities['EKU'] ?? null],
            ['name' => 'Gallagher Combined School', 'municipality_id' => $municipalities['EKU'] ?? null],
            ['name' => 'Gov Emp Personal Finance', 'municipality_id' => $municipalities['EKU'] ?? null],
            ['name' => 'Government Empl Personal Finance Pty Ltd', 'municipality_id' => $municipalities['EKU'] ?? null],
            ['name' => 'Government Employees Fund', 'municipality_id' => $municipalities['EKU'] ?? null],
            ['name' => 'Hi-Tech Training & Consulting', 'municipality_id' => $municipalities['EKU'] ?? null],
            ['name' => 'Imizi Housing', 'municipality_id' => $municipalities['EKU'] ?? null],
            ['name' => 'Indibano Financial Services', 'municipality_id' => $municipalities['EKU'] ?? null],
            ['name' => 'Joko Investments', 'municipality_id' => $municipalities['EKU'] ?? null],
            ['name' => 'Kapana Social Club', 'municipality_id' => $municipalities['EKU'] ?? null],
            ['name' => 'Legal Wise', 'municipality_id' => $municipalities['EKU'] ?? null],
            ['name' => 'Legae Investment', 'municipality_id' => $municipalities['EKU'] ?? null],
            ['name' => 'Legal and Tax', 'municipality_id' => $municipalities['EKU'] ?? null],
            ['name' => 'Liberty Corporate', 'municipality_id' => $municipalities['EKU'] ?? null],
            ['name' => 'Life Sense Financial Services', 'municipality_id' => $municipalities['EKU'] ?? null],
            ['name' => 'Lion Life - Lion of Africa', 'municipality_id' => $municipalities['EKU'] ?? null],
            ['name' => 'Mabusa Social Club', 'municipality_id' => $municipalities['EKU'] ?? null],
            ['name' => 'Matsose Funeral Undertakers', 'municipality_id' => $municipalities['EKU'] ?? null],
            ['name' => 'Metropolitan Group & Life Ltd', 'municipality_id' => $municipalities['EKU'] ?? null],
            ['name' => 'MMK Financial Solutions', 'municipality_id' => $municipalities['EKU'] ?? null],
            ['name' => 'Mohlala Financial Services', 'municipality_id' => $municipalities['EKU'] ?? null],
            ['name' => 'Motlhago Investments', 'municipality_id' => $municipalities['EKU'] ?? null],
            ['name' => 'Old Mutaul Group', 'municipality_id' => $municipalities['EKU'] ?? null],
            ['name' => 'Old Mutual Group', 'municipality_id' => $municipalities['EKU'] ?? null],
            ['name' => 'Old Mutual Life', 'municipality_id' => $municipalities['EKU'] ?? null],
            ['name' => 'Oupa Nkhwedi Tladi', 'municipality_id' => $municipalities['EKU'] ?? null],
            ['name' => 'Phomolong Club', 'municipality_id' => $municipalities['EKU'] ?? null],
            ['name' => 'Prokato Trading Enterprises', 'municipality_id' => $municipalities['EKU'] ?? null],
            ['name' => 'Rand Mutual Assurance Co Ltd', 'municipality_id' => $municipalities['EKU'] ?? null],
            ['name' => 'Saambou', 'municipality_id' => $municipalities['EKU'] ?? null],
            ['name' => 'SANAC', 'municipality_id' => $municipalities['EKU'] ?? null],
            ['name' => 'Sanlam Sky Solutions', 'municipality_id' => $municipalities['EKU'] ?? null],
            ['name' => 'Scorpion - Legal Protection', 'municipality_id' => $municipalities['EKU'] ?? null],
            ['name' => 'Service Staff Provident Fund', 'municipality_id' => $municipalities['EKU'] ?? null],
            ['name' => 'Southern Ambition', 'municipality_id' => $municipalities['EKU'] ?? null],
            ['name' => 'Soweto Country Club', 'municipality_id' => $municipalities['EKU'] ?? null],
            ['name' => 'Stangen Limited', 'municipality_id' => $municipalities['EKU'] ?? null],
            ['name' => 'Transnet', 'municipality_id' => $municipalities['EKU'] ?? null],
            ['name' => 'TS Mphake Social Club', 'municipality_id' => $municipalities['EKU'] ?? null],
            ['name' => 'Tswaranang Social Club', 'municipality_id' => $municipalities['EKU'] ?? null],
            ['name' => 'uMalusi Investment Group', 'municipality_id' => $municipalities['EKU'] ?? null],
            ['name' => 'We Buy Cars', 'municipality_id' => $municipalities['EKU'] ?? null],
            ['name' => 'Workmens Compensation Fund', 'municipality_id' => $municipalities['EKU'] ?? null],
            ['name' => 'letsatsi', 'municipality_id' => $municipalities['EKU'] ?? null],

            // ---------------------------------------------
            // Mogale City (MOG)
            // ---------------------------------------------
            ['name' => 'Absa Vehicle & Asset Finance', 'municipality_id' => $municipalities['MOG'] ?? null],
            ['name' => 'AJMS Group', 'municipality_id' => $municipalities['MOG'] ?? null],
            ['name' => 'Allenridge Secondary School', 'municipality_id' => $municipalities['MOG'] ?? null],
            ['name' => 'AOG Church', 'municipality_id' => $municipalities['MOG'] ?? null],
            ['name' => 'Bontleng Ba Mmoho Social Club', 'municipality_id' => $municipalities['MOG'] ?? null],
            ['name' => 'Buffalo Insurance Brokers', 'municipality_id' => $municipalities['MOG'] ?? null],
            ['name' => 'Capital Legacy Solutions Pty Ltd', 'municipality_id' => $municipalities['MOG'] ?? null],
            ['name' => 'Channel Life', 'municipality_id' => $municipalities['MOG'] ?? null],
            ['name' => 'Clientele Life Assurance Company Ltd', 'municipality_id' => $municipalities['MOG'] ?? null],
            ['name' => 'Credit Guarantee Insurance Co', 'municipality_id' => $municipalities['MOG'] ?? null],
            ['name' => 'Discovery Life Ltd - Funeral', 'municipality_id' => $municipalities['MOG'] ?? null],
            ['name' => 'Emerald Life CC', 'municipality_id' => $municipalities['MOG'] ?? null],
            ['name' => 'Empower Financial Pty Ltd', 'municipality_id' => $municipalities['MOG'] ?? null],
            ['name' => 'First National Bank - Home loan', 'municipality_id' => $municipalities['MOG'] ?? null],
            ['name' => 'Fundi Capital Pty Ltd', 'municipality_id' => $municipalities['MOG'] ?? null],
            ['name' => 'Indibano Financial Services', 'municipality_id' => $municipalities['MOG'] ?? null],
            ['name' => 'Kapana Social Club', 'municipality_id' => $municipalities['MOG'] ?? null],
            ['name' => 'Legal Wise', 'municipality_id' => $municipalities['MOG'] ?? null],
            ['name' => 'Liberty Corporate', 'municipality_id' => $municipalities['MOG'] ?? null],
            ['name' => 'Life Sense Financial Services', 'municipality_id' => $municipalities['MOG'] ?? null],
            ['name' => 'Old Mutual Group', 'municipality_id' => $municipalities['MOG'] ?? null],
            ['name' => 'Rand Mutual Assurance Co Ltd', 'municipality_id' => $municipalities['MOG'] ?? null],
            ['name' => 'Saambou', 'municipality_id' => $municipalities['MOG'] ?? null],
            ['name' => 'Stangen Limited', 'municipality_id' => $municipalities['MOG'] ?? null],
            ['name' => 'Transnet', 'municipality_id' => $municipalities['MOG'] ?? null],
            ['name' => 'Tsabedze TS', 'municipality_id' => $municipalities['MOG'] ?? null],
            ['name' => 'UMALUSI Investment Group', 'municipality_id' => $municipalities['MOG'] ?? null],
            ['name' => 'Workmens Compensation Fund', 'municipality_id' => $municipalities['MOG'] ?? null],
            ['name' => 'letsatsi', 'municipality_id' => $municipalities['MOG'] ?? null],

            // ---------------------------------------------
            // Emfuleni (EMF)
            // ---------------------------------------------
            ['name' => 'Absa Vehicle & Asset Finance', 'municipality_id' => $municipalities['EMF'] ?? null],
            ['name' => 'Alphasure Underwriting Managers', 'municipality_id' => $municipalities['EMF'] ?? null],
            ['name' => 'Assupol Life Lmt', 'municipality_id' => $municipalities['EMF'] ?? null],
            ['name' => 'AVBOB Mutual Assur Society', 'municipality_id' => $municipalities['EMF'] ?? null],
            ['name' => 'Bayport Financial Services', 'municipality_id' => $municipalities['EMF'] ?? null],
            ['name' => 'Capital Legacy Solutions Pty Ltd', 'municipality_id' => $municipalities['EMF'] ?? null],
            ['name' => 'Channel Life', 'municipality_id' => $municipalities['EMF'] ?? null],
            ['name' => 'Legal Wise', 'municipality_id' => $municipalities['EMF'] ?? null],
            ['name' => 'Old Mutual Group', 'municipality_id' => $municipalities['EMF'] ?? null],

            // ---------------------------------------------
            // Merafong City (MER)
            // ---------------------------------------------
            ['name' => 'Absa Vehicle & Asset Finance', 'municipality_id' => $municipalities['MER'] ?? null],
            ['name' => 'Alphasure Underwriting Managers', 'municipality_id' => $municipalities['MER'] ?? null],
            ['name' => 'Assupol Life Lmt', 'municipality_id' => $municipalities['MER'] ?? null],
            ['name' => 'AVBOB Mutual Assur Society', 'municipality_id' => $municipalities['MER'] ?? null],
            ['name' => 'Bayport Financial Services', 'municipality_id' => $municipalities['MER'] ?? null],
            ['name' => 'Capital Legacy Solutions Pty Ltd', 'municipality_id' => $municipalities['MER'] ?? null],
            ['name' => 'Channel Life', 'municipality_id' => $municipalities['MER'] ?? null],
            ['name' => 'Discovery Life Ltd - Funeral', 'municipality_id' => $municipalities['MER'] ?? null],
            ['name' => 'Emerald Life CC', 'municipality_id' => $municipalities['MER'] ?? null],
            ['name' => 'Empower Financial Pty Ltd', 'municipality_id' => $municipalities['MER'] ?? null],
            ['name' => 'First National Bank - Home loan', 'municipality_id' => $municipalities['MER'] ?? null],
            ['name' => 'Fundi Capital Pty Ltd', 'municipality_id' => $municipalities['MER'] ?? null],
            ['name' => 'Legal Wise', 'municipality_id' => $municipalities['MER'] ?? null],
            ['name' => 'Lion Life - Lion of Africa', 'municipality_id' => $municipalities['MER'] ?? null],
            ['name' => 'MMK Financial Solutions', 'municipality_id' => $municipalities['MER'] ?? null],
            ['name' => 'Old Mutual Group', 'municipality_id' => $municipalities['MER'] ?? null],
            ['name' => 'Rand Mutual Assurance Co Ltd', 'municipality_id' => $municipalities['MER'] ?? null],
            ['name' => 'Retail Financial Services', 'municipality_id' => $municipalities['MER'] ?? null],
            ['name' => 'Workmens Compensation Fund', 'municipality_id' => $municipalities['MER'] ?? null],
            ['name' => 'letsatsi', 'municipality_id' => $municipalities['MER'] ?? null],

            // ---------------------------------------------
            // Rand West City (RWC)
            // ---------------------------------------------
            ['name' => '1 Life Direct Insurance Ltd', 'municipality_id' => $municipalities['RWC'] ?? null],
            ['name' => 'Absa Vehicle & Asset Finance', 'municipality_id' => $municipalities['RWC'] ?? null],
            ['name' => 'Alphasure Underwriting Managers', 'municipality_id' => $municipalities['RWC'] ?? null],
            ['name' => 'Assupol Life Lmt', 'municipality_id' => $municipalities['RWC'] ?? null],
            ['name' => 'AVBOB Mutual Assur Society', 'municipality_id' => $municipalities['RWC'] ?? null],
            ['name' => 'Bayport Financial Services', 'municipality_id' => $municipalities['RWC'] ?? null],
            ['name' => 'Capital Legacy Solutions Pty Ltd', 'municipality_id' => $municipalities['RWC'] ?? null],
            ['name' => 'Channel Life', 'municipality_id' => $municipalities['RWC'] ?? null],
            ['name' => 'Discovery Life Ltd - Funeral', 'municipality_id' => $municipalities['RWC'] ?? null],
            ['name' => 'Emerald Life CC', 'municipality_id' => $municipalities['RWC'] ?? null],
            ['name' => 'Empower Financial Pty Ltd', 'municipality_id' => $municipalities['RWC'] ?? null],
            ['name' => 'First National Bank', 'municipality_id' => $municipalities['RWC'] ?? null],
            ['name' => 'Fundi Capital Pty Ltd', 'municipality_id' => $municipalities['RWC'] ?? null],
            ['name' => 'Legal Wise', 'municipality_id' => $municipalities['RWC'] ?? null],
            ['name' => 'Mohlakeng Social Club', 'municipality_id' => $municipalities['RWC'] ?? null],
            ['name' => 'Old Mutual Group', 'municipality_id' => $municipalities['RWC'] ?? null],
            ['name' => 'Rand Mutual Assurance Co Ltd', 'municipality_id' => $municipalities['RWC'] ?? null],
            ['name' => 'Retail Financial Services', 'municipality_id' => $municipalities['RWC'] ?? null],
            ['name' => 'Stangen Limited', 'municipality_id' => $municipalities['RWC'] ?? null],
            ['name' => 'Tswaranang Social Club', 'municipality_id' => $municipalities['RWC'] ?? null],
            ['name' => 'Workmens Compensation Fund', 'municipality_id' => $municipalities['RWC'] ?? null],
            ['name' => 'letsatsi', 'municipality_id' => $municipalities['RWC'] ?? null],

            // ---------------------------------------------
            // City of Johannesburg (COJ)
            // ---------------------------------------------
            ['name' => 'Assupol Life Lmt', 'municipality_id' => $municipalities['COJ'] ?? null],
            ['name' => 'AVBOB Mutual Assur Society', 'municipality_id' => $municipalities['COJ'] ?? null],
            ['name' => 'Bayport Financial Services', 'municipality_id' => $municipalities['COJ'] ?? null],
            ['name' => 'Capital Legacy Solutions Pty Ltd', 'municipality_id' => $municipalities['COJ'] ?? null],
            ['name' => 'Channel Life', 'municipality_id' => $municipalities['COJ'] ?? null],
            ['name' => 'Data Wallet Pty Ltd', 'municipality_id' => $municipalities['COJ'] ?? null],
            ['name' => 'Discovery Life Ltd - Funeral', 'municipality_id' => $municipalities['COJ'] ?? null],
            ['name' => 'Empower Financial Pty Ltd', 'municipality_id' => $municipalities['COJ'] ?? null],
            ['name' => 'Empower Financial Pty Ltd - Salary Advance', 'municipality_id' => $municipalities['COJ'] ?? null],
            ['name' => 'Legal Wise', 'municipality_id' => $municipalities['COJ'] ?? null],
            ['name' => 'Metropolitan Group & Life Ltd', 'municipality_id' => $municipalities['COJ'] ?? null],
            ['name' => 'Old Mutual Group', 'municipality_id' => $municipalities['COJ'] ?? null],
            ['name' => 'Rand Mutual Assurance Co Ltd', 'municipality_id' => $municipalities['COJ'] ?? null],
            ['name' => 'Sanlam Sky Solutions', 'municipality_id' => $municipalities['COJ'] ?? null],
            ['name' => 'Scorpion - Legal Protection', 'municipality_id' => $municipalities['COJ'] ?? null],
            ['name' => 'Stangen Limited', 'municipality_id' => $municipalities['COJ'] ?? null],
            ['name' => 'Transnet', 'municipality_id' => $municipalities['COJ'] ?? null],
            ['name' => 'We Buy Cars', 'municipality_id' => $municipalities['COJ'] ?? null],
            ['name' => 'Workmens Compensation Fund', 'municipality_id' => $municipalities['COJ'] ?? null],
            ['name' => 'letsatsi', 'municipality_id' => $municipalities['COJ'] ?? null],
            ['name' => 'Old Mutaul Group', 'municipality_id' => $municipalities['COJ'] ?? null],
            ['name' => 'Old Mutual Life', 'municipality_id' => $municipalities['COJ'] ?? null],
            ['name' => 'First National Bank - Home loan', 'municipality_id' => $municipalities['COJ'] ?? null],

            // ---------------------------------------------
            // City of Johannesburg (JHB - alias)
            // ---------------------------------------------
            ['name' => 'Empower Financial Pty Ltd', 'municipality_id' => $municipalities['JHB'] ?? null],
            ['name' => 'Legal Wise', 'municipality_id' => $municipalities['JHB'] ?? null],
            ['name' => 'Old Mutual Group', 'municipality_id' => $municipalities['JHB'] ?? null],

            // ---------------------------------------------
            // West Coast District Municipality (WCDM)
            // ---------------------------------------------
            ['name' => 'Capital Legacy Solutions Pty Ltd', 'municipality_id' => $municipalities['WCDM'] ?? null],
            ['name' => 'letsatsi', 'municipality_id' => $municipalities['WCDM'] ?? null],
            ['name' => 'Metropolitan Group & Life Ltd', 'municipality_id' => $municipalities['WCDM'] ?? null],
            ['name' => 'Old Mutaul Group', 'municipality_id' => $municipalities['WCDM'] ?? null],
            ['name' => 'Old Mutual Life', 'municipality_id' => $municipalities['WCDM'] ?? null],
            ['name' => 'Old Mutual Group', 'municipality_id' => $municipalities['WCDM'] ?? null],

            // ---------------------------------------------
            // George (GEO)
            // ---------------------------------------------
            ['name' => 'Capital Legacy Solutions Pty Ltd', 'municipality_id' => $municipalities['GEO'] ?? null],
            ['name' => 'Channel Life', 'municipality_id' => $municipalities['GEO'] ?? null],
            ['name' => 'Legal Wise', 'municipality_id' => $municipalities['GEO'] ?? null],
            ['name' => 'Metropolitan Group & Life Ltd', 'municipality_id' => $municipalities['GEO'] ?? null],
            ['name' => 'Old Mutual Group', 'municipality_id' => $municipalities['GEO'] ?? null],
            ['name' => 'Old Mutual Life', 'municipality_id' => $municipalities['GEO'] ?? null],
            ['name' => 'Sanlam Sky Solutions', 'municipality_id' => $municipalities['GEO'] ?? null],
            ['name' => 'letsatsi', 'municipality_id' => $municipalities['GEO'] ?? null],
        ];

        foreach ($companies as $company) {
            // Skip any entry where municipality_id could not be resolved
            if (empty($company['municipality_id'])) {
                logger()->warning('CompaniesSeeder: municipality_id missing for company', [
                    'name' => $company['name'] ?? null,
                ]);
                continue;
            }

            DB::table('companies')->updateOrInsert(
                [
                    'name' => $company['name'],
                    'municipality_id' => $company['municipality_id'],
                ],
                [
                    'name' => $company['name'],
                    'municipality_id' => $company['municipality_id'],
                    'status' => 'active',
                    'registration_number' => null,
                    'contact_email' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
}
