<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;

class Polishes extends Model
{
    protected $table    = 'polishes';
    protected $fillable = ['name', 'product_url', 'brand_id', 'is_available_online', 'polish_type',
                           'location_id', 'shade', 'collection', 'release_date'
    ];

    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }

    public function location()
    {
        return $this->belongsTo(Locations::class);
    }

    /**
     * @param string $brand_name
     * @return string|null
     */
    private function getBaseUrl(string $brand_name): ?string
    {
        return match ($brand_name) {
            'Holo Taco'      => "https://www.holotaco.com",
            'Mooncat'        => "https://www.mooncat.com",
            'ILNP'           => "https://www.ilnp.com",
            'Painted Polish' => "https://www.paintedpolish.com",
            'Rogue Lacquer'  => "https://www.roguelacquer.com",
            'Orly'           => "https://www.orlybeauty.com",
            default          => null,
        };
    }

    /**
     * @param int    $brandId
     * @param string $name
     * @return string|null
     */
    public function createUrl(int $brandId, string $polish_name): ?string
    {
        $brand    = Brand::findOrFail($brandId);
        $base_url = $this->getBaseUrl($brand->name);
        $path     = $this->getUrlPath($brand->name, $polish_name);

        if ($brand && $base_url && $path) {
            $url = $base_url . $path;
            if ($this->checkUrl($url)) {
                return $url;
            }
        }
        // If URL check failed or base/path is missing
        return null;
    }

    private function getUrlPath(string $brand_name, string $polish_name): ?string
    {
        return match ($brand_name) {
            'Holo Taco', 'Mooncat' => "/products/" . Str::slug($polish_name, '-'),
            'ILNP'                 => "/" . Str::slug($polish_name, '-'),
            'Painted Polish'       => "/product/" . Str::slug($polish_name, '-'),
            'Rogue Lacquer'        => "/collections/polish/products/" . Str::slug($polish_name, '-'),
            default                => null,
        };
    }

    /**
     * @param string $url
     * @return bool
     */
    private function checkUrl(string $url): bool
    {
        try {
            $response = Http::head($url); // fast + light-weight
            return $response->status() === 200;
        } catch (\Exception $e) {
            return false; // fail gracefully
        }
    }
}
