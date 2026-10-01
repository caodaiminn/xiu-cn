// Parse a browser DOM snapshot already returned by the supported browser API.
// No network, hidden state, script evaluation in the page, or article copying.
export function parseVisibleFact(text, url) {
  const lines = text.split('\n');
  const value = line => {
    const v = line.split(': ').slice(1).join(': ').trim();
    if (v.startsWith('"')) { try { return JSON.parse(v); } catch {} }
    return v;
  };
  const heading = lines.find(line => /heading .*\[level=1\]/.test(line));
  if (!heading) throw Error('missing visible detail heading');
  const title = heading.match(/heading "(.*?)" \[level=1\]/)?.[1];
  const header = text.split(heading)[0];
  const breadcrumbs = [...header.matchAll(/link "([^"\n]*)":\s*\n\s*- \/url: (\/[^\s]+\/wx-[^\s]+)/g)];
  const cityMatch = header.match(/link "([^"\n>]+)百修网/);
  if (!breadcrumbs.length || !cityMatch || !title) throw Error('missing city/category breadcrumbs');
  const city = cityMatch[1];
  const following = label => {
    const i = lines.findIndex(line => value(line) === label);
    return i >= 0 && lines[i+1] ? value(lines[i+1]) : '';
  };
  const address = following('服务区域') || following('服务范围');
  const published_at = following('发布时间') || following('发布日期');
  const company = following('入驻商家') || text.match(/企业信息[\s\S]*?link "([^"\n]+)":\s*\n\s*- \/url: \/usershop\/index-/)?.[1];
  const basic = text.split(heading)[1].split('温馨提示')[0];
  const phone = basic.match(/(?:联系电话[：:]\s*)?((?:400|800)[- ]?\d{3,4}[- ]?\d{3,4}|1[3-9]\d{9}|0\d{2,3}[- ]\d{7,8})/)?.[1];
  if (!address?.startsWith(city) || !company || !phone || !/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/.test(published_at)) throw Error('missing or inconsistent visible fields');
  const strip = name => name.startsWith(city) ? name.slice(city.length) : name;
  return {
    source_url:url, source_title:title, city, source_category:strip(breadcrumbs.at(-1)[1]),
    category_breadcrumbs:breadcrumbs.map(item=>strip(item[1])),
    source_category_url:'https://www.baixiu.org'+breadcrumbs.at(-1)[2],
    address, company, contact:following('联系人') || company, phone, published_at,
    collected_at:new Date().toISOString()
  };
}
