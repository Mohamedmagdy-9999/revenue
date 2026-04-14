<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});
   
Route::post('login', 'AuthApiController@login');

Route::get('subtract-year/{id}', 'LicenseApiController@subtractYear');

Route::middleware('jwt.auth')->group(function () {
    Route::post('logout', 'AuthApiController@logout');
    Route::get('application/print/{number}', 'LicensesController@print_form');
    
    Route::get('get_renewal_details/{id}', 'LicenseApiController@get_renewal_details');
    
    Route::get('user_profile', 'AuthApiController@user_profile');
    
    Route::post('update_profile', 'AuthApiController@update_profile');

    Route::get('countries', 'ApiController@countries');
    
    Route::get('banks', 'ApiController@banks');
    
    Route::get('payment_types', 'ApiController@payment_types');
    
    Route::get('currencies', 'ApiController@currencies');
    
    Route::get('governorates', 'ApiController@governorates');
    
    Route::get('directorates/{id}', 'ApiController@directorates');
    
    Route::get('all_directorates', 'ApiController@all_directorates');
    
    Route::get('departments', 'ApiController@departments');
    
    Route::get('prices', 'ApiController@prices');
    
    Route::get('identity_types', 'ApiController@identity_types');
    
    Route::get('categories', 'ApiController@categories');
    
    Route::get('sub_categories/{id}', 'ApiController@sub_categories');
    
     Route::get('second_child/{category_id}/{sub_code}', 'ApiController@second_child');
    
     Route::get('qualifications', 'ApiController@qualifications');
    
    Route::get('specializations/{id}', 'ApiController@specializations');
    
    
    Route::get('branch_types', 'ApiController@branch_types');
    
    Route::get('services', 'ApiController@services');
    
    Route::get('rental_types', 'ApiController@rental_types');
    
     Route::get('panel_types', 'ApiController@panel_types');
     
     Route::get('status', 'ApiController@status');
     
     Route::get('filter_service_by_category', 'ApiController@filter_service_by_category');
     
     
     Route::get('search_by_service_name', 'ApiController@search_by_service_name');
    
    
    
    
   Route::post('add_new_application', 'LicensesController@add_application');
   //Route::post('add_new_application', 'LicensesController@add_application')
   // ->middleware('throttle:2,1');
    

    
    Route::post('add_new_customer', 'CustomerApiController@add_new_customer');
    Route::get('get_customer_by_id/{id}', 'CustomerApiController@get_customer_by_id');
    Route::post('update_customer/{id}', 'CustomerApiController@update_customer');
    Route::get('search_customer', 'CustomerApiController@search_customer');
    Route::get('all_customers', 'CustomerApiController@all_customers');
    Route::get('get_customer_licenses/{id}', 'CustomerApiController@get_customer_licenses');
    
    Route::get('get_customer_licenses_profile/{id}', 'CustomerApiController@get_customer_licenses_profile');
    
    Route::get('pending_licenses', 'CustomerApiController@pending_licenses');
    
    Route::get('finance_licenses', 'CustomerApiController@finance_licenses');
    Route::get('matching_table', 'CustomerApiController@matching_table');
    Route::get('nzafa_licenses', 'CustomerApiController@nzafa_licenses');
    Route::get('matching_nzafa_table', 'CustomerApiController@matching_nzafa_table');
    Route::get('all_licenses', 'CustomerApiController@all_licenses');
    
    Route::get('not_pending_licenses', 'CustomerApiController@not_pending_licenses');
    
    Route::get('license_branches/{id}', 'LicenseApiController@license_branches');
    
    
    
    Route::post('add_new_license', 'LicenseApiController@add_new_license');
    Route::post('add_new_branch', 'LicenseApiController@add_new_branch');
    Route::post('renewal_branch', 'LicenseApiController@renewal_branch');
    
    Route::get('search_by_customer_name', 'CustomerApiController@search_by_customer_name');
    
    Route::get('filter_customer', 'CustomerApiController@filter_customer');
    
    
    Route::get('pending_search_by_customer_name', 'CustomerApiController@pending_search_by_customer_nلثفame');
    
    Route::get('pending_filter_customer', 'CustomerApiController@pending_filter_customer');
    
    
    Route::get('get_branch_details/{id}', 'LicenseApiController@get_branch_details');
    
    
    Route::post('approve_branch_renewal/{id}', 'LicenseApiController@approve_branch_renewal');
    
    Route::post('refuse_branch_renewal/{id}', 'LicenseApiController@refuse_branch_renewal');
    
    Route::get('logs/{id}', 'LicenseApiController@logs');
    
    Route::post('update_branch_renewal/{id}', 'LicenseApiController@update_branch_renewal');
   
    
    
    Route::post('add_service_to_license', 'LicenseApiController@add_service_to_license');
    
    Route::post('remove_service_from_license', 'LicenseApiController@remove_service_from_license');
    
    Route::post('deactivate_branch', 'LicenseApiController@deactivate_branch');
    
    
    Route::post('print_portfolio/{id}', 'LicenseApiController@print_portfolio');
    
    Route::post('finance_approve/{id}', 'LicenseApiController@finance_approve');
    Route::post('nzafa_approve/{id}', 'LicenseApiController@nzafa_approve');
    Route::post('print_license/{id}', 'LicenseApiController@print_license');
    
    Route::get('tax_types', 'ApiController@tax_types');
    
    
    Route::get('checkTaxFile/{customer_id}', 'CustomerApiController@checkTaxFile');
    
    
    Route::post('add_tax_file', 'TaxApiController@add_tax_file');
    Route::post('add_balance', 'TaxApiController@add_balance');
    
    Route::get('get_balance_details/{id}', 'TaxApiController@get_balance_details');
    
    
    Route::post('approve_balance/{id}', 'TaxApiController@approve_balance');
    
    Route::post('refuse_balance/{id}', 'TaxApiController@refuse_balance');
    
    Route::post('update_balance/{id}', 'TaxApiController@update_balance');
    
    Route::get('declarations', 'ApiController@declarations');
    
    Route::get('tax_reviewer_table', 'TaxApiController@tax_reviewer_table');
    
    Route::get('filter_by_tax_file_id/{id}', 'TaxApiController@filter_by_tax_file_id');
    
    Route::post('finance_accept_balance/{id}', 'TaxApiController@finance_accept_balance');
    
    
    
     Route::get('tax_table', 'TaxApiController@tax_table');
    
     Route::get('unit_types', 'ApiController@unit_types');
     
     Route::post('free_professions', 'TaxApiController@free_professions');
     
     Route::post('update_free_professions/{id}', 'TaxApiController@update_free_professions');
     
     
     Route::post('approve_free_professions/{id}', 'TaxApiController@approve_free_professions');
     
     
      Route::post('refuse_free_professions/{id}', 'TaxApiController@refuse_free_professions');
      
      
       Route::get('search_balance', 'TaxApiController@search_balance');
       
       Route::get('search_balance_by_status', 'TaxApiController@search_balance_by_status');
       
       Route::get('tabs', 'TaxApiController@tabs');
    
    
    Route::post('print_free_professions/{id}', 'TaxApiController@print_free_professions');
    
     Route::post('finance_free_professions/{id}', 'TaxApiController@finance_free_professions');
     
     
     Route::get('customer_activities/{id}', 'TaxApiController@customer_activities');
    
    
     Route::post('large_taxpayers', 'TaxApiController@large_taxpayers');
     
     Route::post('update_large_taxpayers/{id}', 'TaxApiController@update_large_taxpayers');
     
     Route::post('approve_large_taxpayers/{id}', 'TaxApiController@approve_large_taxpayers');
     
     
     Route::post('refuse_large_taxpayers/{id}', 'TaxApiController@refuse_large_taxpayers');
     
     
     Route::post('print_large_taxpayers/{id}', 'TaxApiController@print_large_taxpayers');
     
     
     Route::post('finance_large_taxpayers/{id}', 'TaxApiController@finance_large_taxpayers');
     
     
     
     Route::post('meduim_taxpayers', 'TaxApiController@meduim_taxpayers');
     
     Route::post('update_meduim_taxpayers/{id}', 'TaxApiController@update_meduim_taxpayers');
     
     Route::post('approve_meduim_taxpayers/{id}', 'TaxApiController@approve_meduim_taxpayers');
     
     
     Route::post('refuse_meduim_taxpayers/{id}', 'TaxApiController@refuse_meduim_taxpayers');
     
     
     Route::post('print_meduim_taxpayers/{id}', 'TaxApiController@print_meduim_taxpayers');
     
     
     Route::post('finance_meduim_taxpayers/{id}', 'TaxApiController@finance_meduim_taxpayers');
     
     
     
     Route::post('small_taxpayers', 'TaxApiController@small_taxpayers');
     
     Route::post('update_small_taxpayers/{id}', 'TaxApiController@update_meduim_taxpayers');
     
     Route::post('approve_small_taxpayers/{id}', 'TaxApiController@approve_meduim_taxpayers');
     
     
     Route::post('refuse_small_taxpayers/{id}', 'TaxApiController@refuse_meduim_taxpayers');
     
     
     Route::post('print_small_taxpayers/{id}', 'TaxApiController@print_meduim_taxpayers');
     
     
     Route::post('finance_small_taxpayers/{id}', 'TaxApiController@finance_meduim_taxpayers');
    
    
    Route::get('tax_table_by_id/{id}', 'TaxApiController@tax_table_by_id');
    
     Route::get('owned_properties/{id}', 'TaxApiController@owned_properties');
     
     
     Route::post('real_state', 'TaxApiController@real_state');
     Route::post('update_real_state/{id}', 'TaxApiController@update_real_state');
     
     Route::post('approve_real_state/{id}', 'TaxApiController@approve_real_state');
    
    Route::post('refuse_real_state/{id}', 'TaxApiController@refuse_real_state');
    
    Route::post('print_real_state/{id}', 'TaxApiController@print_real_state');
    Route::post('finance_real_state/{id}', 'TaxApiController@finance_real_state');
    
    Route::get('search_services','ApiController@search_services');
    
    Route::get('service_type','ApiController@service_type');
    
    Route::get('count_tabs','CustomerApiController@tabs');
    
     Route::get('get_details/{id}','CustomerApiController@get_details');
     
     Route::get('get_logs_taxes/{id}','TaxApiController@get_logs_taxes');
     
      Route::get('get_tax_details/{id}','TaxApiController@get_tax_details');
      Route::get('get_customer_tax_details/{id}','TaxApiController@get_customer_tax_details');
      
      Route::get('customers_tax_balances/{id}', 'TaxApiController@customers_tax_balances');
      
      Route::post('add_zakah_number', 'ZakahApiController@add_zakah_number');
      
      Route::get('zakah_types', 'ZakahApiController@zakah_types');
      
      Route::get('zakah_declarations', 'ApiController@zakah_declarations');
      
      Route::post('add_zakah_balance', 'ZakahApiController@add_zakah_balance');
      
      Route::post('approve_zakah_balance/{id}', 'ZakahApiController@approve_zakah_balance');
      
      Route::post('refuse_zakah_balance/{id}', 'ZakahApiController@refuse_zakah_balance');
      
      Route::post('update_zakah_balance/{id}', 'ZakahApiController@update_zakah_balance');
      Route::post('print_zakah/{id}', 'ZakahApiController@print_zakah');
      
      Route::post('finance_accept_zakaha_balance/{id}', 'ZakahApiController@finance_accept_zakaha_balance');
      
      Route::get('filter_by_zakah_number_id/{id}', 'ZakahApiController@filter_by_zakah_number_id');
       Route::get('zakah_reviewer_table', 'ZakahApiController@zakah_reviewer_table');
       
       Route::get('zakah_finance_table', 'ZakahApiController@zakah_finance_table');
       
       Route::get('zakah_table', 'ZakahApiController@zakah_table');
       
        Route::get('get_zakah_balance/{id}', 'ZakahApiController@get_zakah_balance');
        
         Route::get('zakah_tabs', 'ZakahApiController@zakah_tabs');
         
         Route::get('get_logs_zakah/{id}', 'ZakahApiController@get_logs_zakah');
         
         Route::get('get_customer_zakah_details/{id}', 'ZakahApiController@get_customer_zakah_details');
         
         Route::post('add_zakah', 'ZakahApiController@add_zakah');
    
         Route::post('update_zakah/{id}', 'ZakahApiController@update_zakah');
         
         Route::post('approve_zakah/{id}', 'ZakahApiController@approve_zakah');
        Route::post('refuse_zakah/{id}', 'ZakahApiController@refuse_zakah');
         Route::post('print_zakah/{id}', 'ZakahApiController@print_zakah');
         Route::post('finance_zakah/{id}', 'ZakahApiController@finance_zakah');
         
        Route::get('get_customer_zakah_balance/{id}', 'ZakahApiController@get_customer_zakah_balance');
        
        Route::get('get_customer_declaration_details/{customer_id}/{zakah_type_id}', 'ZakahApiController@get_customer_declaration_details');
 
          Route::post('approve_pulck_finance', 'LicenseApiController@approve_pulck_finance');
          
          Route::post('refuse_pulck_finance', 'LicenseApiController@refuse_pulck_finance');
          
          
          Route::post('approve_finance/{id}', 'LicenseApiController@approve_finance');
          
          Route::post('refuse_finance/{id}', 'LicenseApiController@refuse_finance');
          
          Route::post('update_finance/{id}', 'LicenseApiController@update_finance');
          
          Route::post('approve_pulck_nzafa', 'LicenseApiController@approve_pulck_nzafa');
          
          Route::post('refuse_pulck_nzafa', 'LicenseApiController@refuse_pulck_nzafa');
          
          Route::post('approve_nzafa/{id}', 'LicenseApiController@approve_nzafa');
          
          Route::post('refuse_nzafa/{id}', 'LicenseApiController@refuse_nzafa');
        
         Route::post('update_nzafa/{id}', 'LicenseApiController@update_nzafa');
         
         Route::get('zakah_reviewer_mehdar_table', 'ZakahApiController@zakah_reviewer_mehdar_table');
         Route::post('approve_mehdar/{id}', 'ZakahApiController@approve_mehdar');
         Route::post('refuse_mehdar/{id}', 'ZakahApiController@refuse_mehdar');
         
         Route::get('customers_zakah_balances/{id}', 'ZakahApiController@customers_zakah_balances');
         
         Route::post('approve_mehdar_zakah/{id}', 'ZakahApiController@approve_mehdar_zakah');
         Route::post('refuse_mehdar_zakah/{id}', 'ZakahApiController@refuse_mehdar_zakah');
         
      Route::post('supply_print_finance_by_ids','LicenseApiController@supply_print_finance_by_ids');
      
    Route::get('supply_print_finance','LicenseApiController@supply_print_finance');
     Route::get('liquidation_of_custody','LicenseApiController@liquidation_of_custody');
   
     Route::get('branches_renewals/{id}', 'LicenseApiController@branches_renewals');
         
    Route::post('add_liquidation_bank','LicenseApiController@add_liquidation_bank');
         
         
          Route::get('supply_print_nzafa', 'LicenseApiController@supply_print_nzafa');
          
          Route::post('supply_print_nzafa_by_ids', 'LicenseApiController@supply_print_nzafa_by_ids');
          
           Route::get('liquidation_of_custody_nzafa', 'LicenseApiController@liquidation_of_custody_nzafa');
           
        Route::post('approve_pulck_tax_finance', 'TaxApiController@approve_pulck_tax_finance');
        
        Route::post('approve_tax_finance/{id}', 'TaxApiController@approve_tax_finance');
        
         Route::post('refuse_tax_finance/{id}', 'TaxApiController@refuse_tax_finance');
           
           
           Route::get('matching_tax_table', 'TaxApiController@matching_tax_table');
           
    Route::get('tax_finance_table', 'TaxApiController@tax_finance_table');
    Route::get('supply_print_tax', 'TaxApiController@supply_print_tax');
    
    Route::post('supply_print_tax_by_ids', 'TaxApiController@supply_print_tax_by_ids');
    
    
    Route::get('liquidation_of_custody_tax', 'TaxApiController@liquidation_of_custody_tax');
           
    Route::post('update_finance_tax/{id}', 'TaxApiController@update_finance_tax');
    
    
    Route::post('approve_pulck_zakah_finance', 'ZakahApiController@approve_pulck_zakah_finance');
    
    Route::post('approve_zakah_finance/{id}', 'ZakahApiController@approve_zakah_finance');
    
    Route::post('refuse_zakah_finance/{id}', 'ZakahApiController@refuse_zakah_finance');
    
    Route::post('update_finance_zakah/{id}', 'ZakahApiController@update_finance_zakah');
    
    Route::get('matching_zakah_table', 'ZakahApiController@matching_zakah_table');
    
    Route::get('supply_print_zakah', 'ZakahApiController@supply_print_zakah');
    
    Route::get('liquidation_of_custody_zakah', 'ZakahApiController@liquidation_of_custody_zakah');
         
         
         Route::post('supply_print_zakah_by_ids', 'ZakahApiController@supply_print_zakah_by_ids');
         
         Route::get('tax_status', 'DashboardApiController@tax_status');
         
         Route::get('zakah_status', 'DashboardApiController@zakah_status');
         
         Route::get('search_taxpayer', 'CustomerApiController@search_taxpayer');
         
         Route::get('branch_services/{id}', 'LicenseApiController@getBranchAllServices');
         
    Route::prefix('dashboard')->group(function () {
    
        Route::get('get_bank', 'DashboardApiController@get_bank');
        Route::post('add_bank', 'DashboardApiController@add_bank');
        Route::post('edit_bank/{id}', 'DashboardApiController@edit_bank');
        Route::post('toggle_bank/{id}', 'DashboardApiController@toggle_bank');
        
         Route::get('get_currency', 'DashboardApiController@get_currency');
         Route::post('add_currency', 'DashboardApiController@add_currency');
         Route::post('edit_currency/{id}', 'DashboardApiController@edit_currency');
         
        Route::get('get_country', 'DashboardApiController@get_country');
        Route::post('add_country', 'DashboardApiController@add_country');
        Route::post('edit_country/{id}', 'DashboardApiController@edit_country');
        Route::post('toggle_country/{id}', 'DashboardApiController@toggle_country');
        
        Route::get('get_customer', 'DashboardApiController@get_customer');
        Route::post('add_customer', 'DashboardApiController@add_customer');
        Route::post('edit_customer/{id}', 'DashboardApiController@edit_customer');
    
        Route::post('add_application', 'DashboardApiController@add_application');
        
        Route::get('get_service_type', 'DashboardApiController@get_service_type');
        
        Route::post('add_service_type', 'DashboardApiController@add_service_type');
        
        Route::post('edit_service_type/{id}', 'DashboardApiController@edit_service_type');
        
        Route::get('get_service', 'DashboardApiController@get_service');
        
        Route::post('add_service', 'DashboardApiController@add_service');
        
        Route::post('edit_service/{id}', 'DashboardApiController@edit_service');
        
        Route::post('toggle_services/{id}', 'DashboardApiController@toggle_services');
        
        
        Route::get('license', 'DashboardApiController@license');
         Route::get('license_count', 'DashboardApiController@license_count');
          
          
         Route::get('tax', 'DashboardApiController@tax');
         Route::get('tax_count', 'DashboardApiController@tax_count');
         
         Route::get('zakah', 'DashboardApiController@zakah');
         Route::get('zakah_count', 'DashboardApiController@zakah_count');
         Route::get('users', 'DashboardApiController@users');
         Route::get('departments', 'DashboardApiController@departments');
         Route::post('add_user', 'DashboardApiController@add_user');
         Route::post('edit_user/{id}', 'DashboardApiController@edit_user');
         
         Route::post('toggle_user/{id}', 'DashboardApiController@toggle_user');
         
        
        Route::post('add_directorate', 'DashboardApiController@add_directorate');
        Route::post('edit_directorate/{id}', 'DashboardApiController@edit_directorate');
        
        Route::post('toggle_directorate/{id}', 'DashboardApiController@toggle_directorate');
        
        Route::post('toggle_currency/{id}', 'DashboardApiController@toggle_currency');
        
        Route::get('summary', 'DashboardApiController@summary');
        Route::get('distribution', 'DashboardApiController@distribution');
        Route::get('trend', 'DashboardApiController@trend');
        Route::get('latest', 'DashboardApiController@latest');
    });
    
    
    
    Route::prefix('reports')->group(function () {
    
        Route::get('revenues_reports', 'ReportsApiController@revenues_reports');
        
    });

    
});

    Route::prefix('v-mobile')->group(function () {
    
        Route::post('register', 'MobileApiController@register');
        Route::post('login', 'MobileApiController@login');
        Route::post('checkUser', 'MobileApiController@checkUser');
        Route::post('activateAccount', 'MobileApiController@activateAccount');

        Route::get('countries', 'MobileApiController@countries');
        Route::get('identity_types', 'MobileApiController@identity_types');

            Route::middleware(['auth:api_customers', 'customer'])->group(function () {

            
                Route::post('customer_change_password', 'MobileApiController@customer_change_password');
                Route::post('delete_customer', 'MobileApiController@delete_customer');

                Route::get('licenses_category', 'MobileApiController@licenses_category');

                Route::get('tax_types', 'MobileApiController@tax_types');

                Route::get('zakah_types', 'MobileApiController@zakah_types');
                Route::get('rental_types', 'MobileApiController@rental_types');
                Route::get('service_types', 'MobileApiController@service_types');

                Route::post('request_license', 'MobileApiController@request_license');
            
            
            });
            
        

    });