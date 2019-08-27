<?php

namespace App\Http\Controllers;

use App\Helpers\AdHelper;
use App\Helpers\WidgetHelper;
use App\Library\BaseUnit;
use App\Models\Article\Article;
use App\Models\Category\Category;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class ArticleController extends Controller
{

    public function show($article_id)
    {

        $article = app()->custom_cache->rememberArticleData($article_id, true);


        if ($article->redirect_url) {
            return redirect($article->redirect_url);
        }
        $locale = app()->getLocale();

        $right_url = $article->url_link_full;
        $current_url = url()->current();

        if ($right_url != $current_url) {
            return redirect($right_url, 301);
        }

        if (!request()->has('preview')) {

            if ($article->published_at) {


                $now = Carbon::now();
                $pub_time = Carbon::createFromFormat('Y-m-d H:i:s', $article->published_at);

                if (!$article->pub_status || $now < $pub_time) {
                    abort(404);
                }
            } else {
                abort(404);
            }
        }


        $category = $article->category_main->first();
        $top_parent_category = false;
        if ($category) {
            $top_parent_category = $category;

            while ($top_parent_category->parent) {
                $top_parent_category = $top_parent_category->parent;
            }
        }


        app()->menu->setCurrentCategory($category);

        if ($translate_uk = $article->translate('uk')) {
            view()->share('change_lang_url_uk', url('uk/' . $translate_uk->slug_full));
        }

        if ($translate_ru = $article->translate('ru')) {
            view()->share('change_lang_url_ru', url($translate_ru->slug_full));
        }

        Redis::incr('article_views:' . $article_id);


        $this->prepareAdCodeForHead($category, $article);


        $right_column_rendered = false;
        if ($category) {
            $right_column_rendered = WidgetHelper::getWidgetsByCategoryId($category->id); //implode('',$right_column_rendered);
        }

        if ($article->type == Article::LONGREAD) {
            return view('client-side.main_pages.longread.article.index', compact(
                'article',
                'right_column_rendered'
            ));
        }

        if ($top_parent_category) {
            if ($top_parent_category->id == Category::TECHNO_ID) {
                return view('client-side.main_pages.techno.article.index', compact(
                    'article',
                    'right_column_rendered'
                ));
            } elseif ($top_parent_category->id == Category::STYLE_ID) {
                return view('client-side.main_pages.style.article.index', compact(
                    'article',
                    'right_column_rendered'
                ));

            } elseif ($top_parent_category->id == Category::BIZNES_ID) {
                return view('client-side.main_pages.business.article.index', compact(
                    'article',
                    'right_column_rendered'
                ));

            }
        }
        return view('client-side.main_pages.main.article.index', compact(
            'article',
            'right_column_rendered'
        ));


    }

    public function loadScrolledArticles($article_id)
    {

        $autoload_articles = app()->custom_cache->rememberArticleAutoloadArticles($article_id, false);

        return response()->json($autoload_articles);

    }

    public function showAmp($article_id)
    {


        $article = Article::findOrFail($article_id);

        return view('client-side.main_pages.main.article.index_amp', compact('article'));
    }

    public function incrementShareCounts($id)
    {

        Redis::incr('article_shares:' . $id);
    }

    private function prepareAdCodeForHead($category, $article)
    {

        $adHelper = new AdHelper([]);

        if ($category) {
            $adHelper->setTargetingRazdelValue($category->id);
        }
        $adHelper->setTargetingArticleValue($article->id);

        $include_branding_code = $adHelper->getBrandingBody();
        $include_branding_head = $adHelper->getBrandingHead();

        $include_ad_code_to_head = $adHelper->processCodeWithTargeting();

        view()->share('include_ad_code_to_head', $include_ad_code_to_head);
        view()->share('include_branding_code', $include_branding_code);
        view()->share('include_branding_head', $include_branding_head);
    }

}
