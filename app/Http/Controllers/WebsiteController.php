<?php

namespace App\Http\Controllers;

use Artisan;
use File;
use Illuminate\Http\Request;

class WebsiteController extends Controller {
  public function __construct() {
    // Staff Permission Check
    $this->middleware(['permission:header_setup'])->only('header');
    $this->middleware(['permission:footer_setup'])->only('footer');
    $this->middleware(['permission:view_all_website_pages'])->only('pages');
    $this->middleware(['permission:website_appearance'])->only('appearance');
  }

  public function header(Request $request) {
    return view('backend.website_settings.header');
  }
  public function footer(Request $request) {
    $lang = $request->lang;
    return view('backend.website_settings.footer', compact('lang'));
  }
  public function pages(Request $request) {
    return view('backend.website_settings.pages.index');
  }
  public function appearance(Request $request) {
    return view('backend.website_settings.appearance');
  }
  public function sitemap(Request $request) {
    $content = File::get(base_path('sitemap.xml'));
    return view('backend.website_settings.sitemap', compact('content'));
  }
  public function robots(Request $request) {
    $content = File::get(base_path('robots.txt'));
    return view('backend.website_settings.robots', compact('content'));
  }
  public function updateSitemap(Request $request) {
    File::put(base_path('sitemap.xml'), $request->content);
    Artisan::call('cache:clear');
    flash(translate("Sitemap updated successfully"))->success();
    return back();
  }
  public function updateRobots(Request $request) {
    File::put(base_path('robots.txt'), $request->content);
    Artisan::call('cache:clear');
    flash(translate("Robots updated successfully"))->success();
    return back();
  }
}
