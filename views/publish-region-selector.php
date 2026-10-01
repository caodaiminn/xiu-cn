<?php
$publishCities=cityList(); $publishCityMap=array_column($publishCities,null,'id');
$selectedCity=$publishCityMap[(int)$value('city_id',0)]??null;
$selectedProvince=(string)$value('province',$selectedCity['province']??'');
?>
<div class="publish-service-region" role="group" aria-labelledby="listing-region-label">
 <span class="publish-region-title" id="listing-region-label"><span aria-hidden="true">*</span> 服务区域：</span>
 <div class="publish-region-selects">
  <label><span class="sr-only">省份</span><select name="province" id="listing-province" aria-label="省份" required aria-describedby="listing-region-status"><option value="">选择省份</option><?php foreach(cityDirectory() as $province): ?><option value="<?= e($province['name']) ?>" <?= $selectedProvince===$province['name']?'selected':'' ?>><?= e($province['name']) ?></option><?php endforeach ?></select></label>
  <label><span class="sr-only">城市</span><select name="city_id" id="listing-city" aria-label="城市" required aria-describedby="listing-region-status"><option value="">选择城市</option><?php foreach($publishCities as $city): ?><option value="<?= (int)$city['id'] ?>" data-province="<?= e($city['province']) ?>" data-name="<?= e($city['name']) ?>" <?= (int)$value('city_id')===(int)$city['id']?'selected':'' ?>><?= e($city['province'].' · '.$city['name']) ?></option><?php endforeach ?></select></label>
  <label><span class="sr-only">区域</span><select name="district_id" id="listing-district" aria-label="区域" aria-describedby="listing-region-status"><option value="0">选择区域 / 全市</option><?php foreach(districtList() as $district): ?><option data-city="<?= (int)$district['city_id'] ?>" data-name="<?= e($district['name']) ?>" value="<?= (int)$district['id'] ?>" <?= (int)$value('district_id',0)===(int)$district['id']?'selected':'' ?>><?= e($district['city_name'].' · '.$district['name']) ?></option><?php endforeach ?></select></label>
 </div>
 <p class="field-help publish-region-help" id="listing-region-status" aria-live="polite">依次选择省份、城市和区域，也可按全市发布。</p>
 <noscript><p class="field-help publish-region-help">请核对城市所属省份、区域所属城市，提交时会校验对应关系。</p></noscript>
</div>
