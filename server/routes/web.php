<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\BookmarkController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\CreatePostController;
use App\Http\Controllers\DeleteMediaController;
use App\Http\Controllers\DeletePostController;
use App\Http\Controllers\FollowProfileController;
use App\Http\Controllers\FollowRequestController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\HomeTimelineController;
use App\Http\Controllers\IncomingFollowRequestController;
use App\Http\Controllers\LikeController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProfilePrivacyController;
use App\Http\Controllers\PublicTimelineController;
use App\Http\Controllers\RegisterController;
use App\Http\Controllers\RemoveFollowerController;
use App\Http\Controllers\RepostController;
use App\Http\Controllers\SessionController;
use App\Http\Controllers\ShowPostController;
use App\Http\Controllers\UploadMediaController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/health', HealthController::class);

Route::post('/register', RegisterController::class);

Route::post('/login', [SessionController::class, 'store']);
Route::post('/logout', [SessionController::class, 'destroy'])->middleware('auth');

Route::get('/account', AccountController::class)->middleware('auth');

Route::patch('/profile', ProfileController::class)->middleware('auth');
Route::patch('/profile/privacy', ProfilePrivacyController::class)->middleware('auth');

Route::post('/profiles/{profile}/follow', FollowProfileController::class)->middleware('auth');
Route::delete('/profiles/{profile}/follow', [FollowProfileController::class, 'destroy'])->middleware('auth');
Route::delete('/followers/{profile}', RemoveFollowerController::class)->middleware('auth');

Route::get('/follow-requests', IncomingFollowRequestController::class)->middleware('auth');
Route::post('/follow-requests/{followRequest}/accept', [FollowRequestController::class, 'accept'])->middleware('auth');
Route::delete('/follow-requests/{followRequest}', [FollowRequestController::class, 'destroy'])->middleware('auth');

Route::get('/timelines/home', HomeTimelineController::class)->middleware('auth');
Route::get('/timelines/public', PublicTimelineController::class);

Route::post('/media', UploadMediaController::class)->middleware('auth');

Route::delete('/media/{media}', DeleteMediaController::class)->middleware('auth');

Route::post('/posts', CreatePostController::class)->middleware('auth');

Route::get('/posts/{status}', ShowPostController::class);
Route::delete('/posts/{status}', DeletePostController::class)->middleware('auth');

Route::post('/posts/{status}/like', [LikeController::class, 'store'])->middleware('auth');
Route::delete('/posts/{status}/like', [LikeController::class, 'destroy'])->middleware('auth');

Route::post('/posts/{status}/bookmark', [BookmarkController::class, 'store'])->middleware('auth');
Route::delete('/posts/{status}/bookmark', [BookmarkController::class, 'destroy'])->middleware('auth');

Route::post('/posts/{status}/comments', [CommentController::class, 'store'])->middleware('auth');
Route::delete('/comments/{status}', [CommentController::class, 'destroy'])->middleware('auth');

Route::post('/posts/{status}/repost', [RepostController::class, 'store'])->middleware('auth');
Route::delete('/posts/{status}/repost', [RepostController::class, 'destroy'])->middleware('auth');
