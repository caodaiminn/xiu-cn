"""Build the offline PHP dictionary from pinned pypinyin 0.55.0 (MIT).
Only needed to regenerate the dictionary; Python is not a website dependency.
"""
import json
from importlib.metadata import distribution
from pathlib import Path
from pypinyin import Style, lazy_pinyin
from pypinyin.pinyin_dict import pinyin_dict
from pypinyin.contrib.tone_convert import to_normal

root = Path(__file__).resolve().parent.parent
target = root / 'app' / 'data'
target.mkdir(exist_ok=True)
characters = {chr(code): to_normal(readings.split(',')[0]).replace('ü', 'v') for code, readings in pinyin_dict.items()}
phrases = '重庆 长沙 长春 长安 长丰 银行 行业 商行 执行 行动 行程 行为 行驶 行政 空调 中央空调 调整 调节 调试 调度 调用 调查 单调 色调 重新 重启 重复 重要 重叠 重置 严重 重量 乐山 音乐 快乐 了解 解锁 解决 系统 厦门 朝阳 温州 柳州 六安 蚌埠 排水 装载 载重 得到 觉得 瓷砖 渗漏 角色 数量 数码 参数 数据 复印机 热水器 下水道 电机 机械 清洗 制冷 爆炸 空鼓 便宜 墙面 防水 处理 供给 给水 佛山 济南 东莞 沈阳 单县 漯河 丽水 台州 台灯 屏幕 更换 保养 门窗 跑步 维修 专业 房屋 隐藏 中山 乐清'.split()
overrides = {phrase: lazy_pinyin(phrase, style=Style.NORMAL, errors='ignore') for phrase in phrases}
city_data = target / 'nationwide-cities.json'
if city_data.exists():
    for city in json.loads(city_data.read_text(encoding='utf-8'))['cities']:
        overrides[city['name']] = lazy_pinyin(city['name'], style=Style.NORMAL, errors='ignore')
    overrides.update({'漯河': ['luo', 'he'], '朝阳': ['chao', 'yang'], '六安': ['lu', 'an'], '长治': ['chang', 'zhi']})

(target / 'pinyin.json').write_text(json.dumps({'characters': characters, 'phrases': overrides}, ensure_ascii=False, separators=(',', ':')), encoding='utf-8')
package = distribution('pypinyin')
license_file = next(package.locate_file(item) for item in package.files if 'LICENSE' in str(item).upper())
(target / 'PINYIN-LICENSE.txt').write_text('Offline pronunciation data generated from pypinyin 0.55.0.\nSource: https://github.com/mozillazg/python-pinyin\n\n' + license_file.read_text(encoding='utf-8'), encoding='utf-8')
print(f'Built {len(characters)} pronunciations and {len(overrides)} phrase overrides.')
