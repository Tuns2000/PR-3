<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;

class CmsController extends Controller
{
    /**
     * Показать CMS страницу по slug
     */
    public function show(Request $request, string $slug)
    {
        // Простой роутинг для статических страниц
        $pages = [
            'about' => 'About Cassiopeia Space Dashboard',
            'contact' => 'Contact Us',
            'privacy' => 'Privacy Policy',
        ];

        if (!isset($pages[$slug])) {
            abort(404);
        }

        return view('cms.page', [
            'slug' => $slug,
            'title' => $pages[$slug]
        ]);
    }
}
