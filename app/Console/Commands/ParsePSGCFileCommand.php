<?php

namespace App\Console\Commands;

use App\Eloquent\City;
use App\Eloquent\Region;
use App\Eloquent\Barangay;
use App\Eloquent\District;
use App\Eloquent\Province;
use App\Eloquent\Municipality;
use Illuminate\Console\Command;
use App\Eloquent\SubMunicipality;
use Spatie\SimpleExcel\SimpleExcelReader;

class ParsePSGCFileCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'psgc:parse';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Parse PSGC Publication';

    protected $latest = '';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        // source: https://psa.gov.ph/classification/psgc/
        $filePath = storage_path('PSGC-3Q-2022-Publication-Datafile.xlsx');

        $rows = SimpleExcelReader::create($filePath)
            ->fromSheetName('PSGC')
            ->getRows();

        $rows->each(function (array $properties) {
            //skip rows with no code or no geographic level, they are only used as marker/label on sheet
            if (empty($properties['10-digit PSGC']) || empty($properties['Geographic Level'])) {
                return;
            }
            $data = [
                'code' => $properties['10-digit PSGC'],
                'name' => $properties['Name'],
                'level' => $properties['Geographic Level'],
                'city_class' => $properties['City Class'],
                'income_class' => $properties["Income\nClassification"],
                'urban_rural' => $properties["Urban / Rural\n(based on 2020 CPH)"],
                'population' => preg_replace('/\D+/', '', $properties["2020 Population"]),
            ];

            $data = array_filter($data);

            if (isset($data['level'])) {
                $methods = 'process'.$data['level'];

                $this->$methods($data);
            }
        });

        return 0;
    }

    private function processReg($data)
    {
        Region::create($data);

        $this->latest = Region::class;
    }

    private function processProv($data)
    {
        $data['region_id'] = Region::orderBy('id', 'desc')->pluck('id')->first();
        Province::create($data);

        $this->latest = Province::class;
    }

    private function processDist($data)
    {
        $data['region_id'] = Region::orderBy('id', 'desc')->pluck('id')->first();
        District::create($data);

        $this->latest = District::class;
    }

    private function processCity($data)
    {
        $region = Region::orderBy('id', 'desc')->first();
        $province = Province::orderBy('id', 'desc')->first();
        $district = District::orderBy('id', 'desc')->first();

        $geographic = optional($district)->created_at > optional($province)->created_at ? $district : $province;

        // 099701000, 0990101000 - City of Isabela
        // 129804000, 1908703000 - City of Cotabato
        if (in_array($data['code'], ['099701000', '129804000', '0990101000', '1908703000'])) {
            $geographic = $region;
        }

        //if city classification is empty on the sheet, default to CC
        if (empty($data['city_class'])) {
            $data['city_class'] = 'CC';
        }

        $data['geographic_type'] = get_class($geographic);
        $data['geographic_id'] = $geographic->id;
        City::create($data);

        $this->latest = City::class;
    }

    private function processSubMun($data)
    {
        $data['city_id'] = City::orderBy('id', 'desc')->pluck('id')->first();
        SubMunicipality::create($data);

        $this->latest = SubMunicipality::class;
    }

    private function processMun($data)
    {
        $geographic = Province::orderBy('id', 'desc')->first();

        //137606000, 1381701000 Pateros
        if (in_array($data['code'], ['137606000', '1381701000'])) {
            $geographic = District::orderBy('id', 'desc')->first();
        }

        $data['geographic_type'] = get_class($geographic);
        $data['geographic_id'] = $geographic->id;

        Municipality::create($data);

        $this->latest = Municipality::class;
    }

    private function processBgy($data)
    {
        $latest = $this->latest;

        $geographic = (new $latest())->orderBy('id', 'desc')->first();

        $data['geographic_type'] = get_class($geographic);
        $data['geographic_id'] = $geographic->id;
        Barangay::create($data);
    }
}
