<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\BaseController;
use App\Http\Controllers\BusinessInformationController;
use App\Http\Controllers\UsersController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\RolesController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\TagsController;
use App\Http\Controllers\CardController;
use App\Http\Controllers\GiftCardsController;
use App\Http\Controllers\CustomersController;
use App\Http\Controllers\DropdownController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\ReceivingController;
use App\Http\Controllers\SalesController;
use App\Http\Controllers\ReportsController;


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
//AUTH
Route::post('/login', [AuthController::class, 'login']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
});


//ROLES
Route::controller(RolesController::class)->middleware(['auth:sanctum'])->group(function () {
    Route::get('roles', 'getRoles');
    Route::get('roles/{id}', 'getRoleById');
    Route::post('create/roles', 'createRole');
    Route::post('update/roles/{id}', 'updateRole');
    Route::post('roles/{id}/archive', 'archiveRole');
});


//USERS
Route::controller(UsersController::class)->group(function () {
    Route::get('users', 'getAllUsers');
    Route::get('users/{id}', 'getUserById');
    Route::post('create/users', 'createUser');
    Route::post('update/users/{id}', 'updateUser');
    Route::post('users/{id}/archive', 'archiveUser');
});

//CUSTOMERS
Route::controller(CustomersController::class)->middleware(['auth:sanctum'])->group(function () {
    Route::get('customers', 'getCustomers');
    Route::get('customers/{id}', 'getCustomerById');
    Route::post('create/customers', 'createCustomer');
    Route::post('update/customers/{id}', 'updateCustomer');
    Route::post('customers/{id}/archive', 'archiveCustomer');
});


//CATEGORIES
Route::controller(CategoryController::class)->middleware(['auth:sanctum'])->group(function () {
    Route::get('categories', 'getCategories');
    Route::get('categories/{id}', 'getCategoryById');
    Route::post('create/categories', 'createCategory');
    Route::post('update/categories/{id}', 'updateCategory');
    Route::post('categories/{id}/archive', 'archiveCategory');
});


//TAGS
Route::controller(TagsController::class)->middleware(['auth:sanctum'])->group(function () {
    Route::get('tags', 'getTags');
    Route::get('tags/{id}', 'getTagById');
    Route::post('create/tags', 'createTag');
    Route::post('update/tags/{id}', 'updateTag');
    Route::post('tags/{id}/archive', 'archiveTag');
});

//CARDS
Route::controller(CardController::class)->middleware(['auth:sanctum'])->group(function () {
    Route::get('cards', 'getCards');
    Route::get('cards/{id}', 'getCardById');
    Route::post('create/cards', 'createCard');
    Route::post('update/cards/{id}', 'updateCard');
    Route::post('cards/{id}/archive', 'archiveCard');
});



//GIFT CARDS
Route::controller(GiftCardsController::class)->middleware(['auth:sanctum'])->group(function () {
    Route::get('gift-cards', 'getGiftCards');
    Route::get('gift-cards/{id}', 'getGiftCardById');
    Route::post('create/gift-cards', 'createGiftCard');
    Route::post('update/gift-cards/{id}', 'updateGiftCard');
    Route::post('gift-cards/{id}/archive', 'archiveGiftCard');
});

//SUPPLIERS
Route::controller(SupplierController::class)->middleware(['auth:sanctum'])->group(function () {
    Route::get('suppliers', 'getAllSuppliers');
    Route::get('suppliers/{id}', 'getSupplierById');
    Route::post('create/suppliers', 'createSupplier');
    Route::post('update/suppliers/{id}', 'updateSupplier');
    Route::post('suppliers/{id}/archive', 'archiveSupplier');
});

//ITEMS
Route::controller(ItemController::class)->middleware(['auth:sanctum'])->group(function () {
    Route::get('items', 'getAllItems');
    Route::get('items/{id}', 'getItemById');
    Route::post('create/items', 'createItem');
    Route::post('update/items/{id}', 'updateItem');
    Route::post('items/{id}/archive', 'archiveItem');
});

//RECEIVINGS
Route::controller(ReceivingController::class)->middleware(['auth:sanctum'])->group(function () {
    Route::get('receivings', 'getAllReceivings');
    Route::get('receivings/{id}', 'getReceivingById');
    Route::post('create/receivings', 'createReceiving');
    Route::post('complete/receivings/{id}', 'completeReceiving');
});

//SALES
Route::controller(SalesController::class)->middleware(['auth:sanctum'])->group(function () {
    Route::get('sales', 'getAllSales');
    Route::get('sales/{id}', 'getSaleById');
    Route::get('held-sales', 'getHeldSales');
    Route::post('create/sales', 'createSale');
    Route::post('hold/sales', 'holdSale');
    Route::post('complete/held-sale/{id}', 'completeHeldSale');
});




//BUSINESS INFORMATION
Route::controller(BusinessInformationController::class)->middleware(['auth:sanctum'])->group(function () {
    Route::get('get-business-information', 'getBusinessInformation');
    Route::post('save-business-information', 'saveBusinessInformation');
});


//REPORTS
Route::controller(ReportsController::class)->middleware(['auth:sanctum'])->group(function () {
    Route::get('report/sales', 'reportSales'); // filter: daily, weekly, yearly, customer
});


//DROPDOWN
Route::prefix('dropdown')->controller(DropdownController::class)->group(function () {
    Route::get('users', 'getUsers');
    Route::get('categories', 'getCategories');
    Route::get('tags', 'getTags');
    Route::get('cards', 'getCards');
    Route::get('gift-cards', 'getGiftCards');
    Route::get('suppliers', 'getSuppliers');
    Route::get('customers', 'getCustomers');
    Route::get('items', 'getItems');
    Route::get('roles', 'getRoles');
});
