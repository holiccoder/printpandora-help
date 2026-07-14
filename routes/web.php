<?php

use App\Http\Controllers\HelpCenterController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HelpCenterController::class, 'home'])->name('help.home');
Route::get('/hc/search', [HelpCenterController::class, 'search'])->name('help.search');
Route::get('/hc/categories/{category:slug}', [HelpCenterController::class, 'category'])->name('help.category');
Route::get('/hc/sections/{section:slug}', [HelpCenterController::class, 'section'])->name('help.section');
Route::get('/hc/articles/{article:slug}', [HelpCenterController::class, 'article'])->name('help.article');
