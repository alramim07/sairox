<?php

use App\Http\Controllers\CommentController;
use App\Http\Controllers\FrontendController;
use Illuminate\Support\Facades\Route;

Route::get('/', [FrontendController::class, 'home'])->name('home');

Route::get('/posts/{slug}', [FrontendController::class, 'post'])->name('post');
Route::get('/preview/posts/{slug}', [FrontendController::class, 'preview'])
    ->name('preview.post')
    ->middleware('signed');
Route::get('/categories/{slug}', [FrontendController::class, 'category'])->name('category');
Route::get('/tags/{slug}', [FrontendController::class, 'tag'])->name('tag');

Route::post('/comment', [CommentController::class, 'store'])->name('comment.store');

Route::get('/{slug}', [FrontendController::class, 'page'])->name('page');
