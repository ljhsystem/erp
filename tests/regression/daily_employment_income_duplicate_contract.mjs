import fs from 'node:fs';
import assert from 'node:assert/strict';

const page = fs.readFileSync('public/assets/js/pages/institution/daily-employment-income/index.js', 'utf8');
const grid = fs.readFileSync('public/assets/js/pages/institution/daily-employment-income/worker-cards.js', 'utf8');

assert.match(page, /button\.textContent = '그룹 복제'/);
const groupActionOrder = [
    "addWorker.textContent = '+ 작업자 추가'",
    "button.textContent = '그룹 복제'",
    "remove.type = 'button'; remove.className = 'btn btn-outline-danger btn-sm'; remove.textContent = '삭제'",
    "collapse.textContent = group.collapsed ? '펼치기' : '접기'",
].map(token => page.indexOf(token));
assert.ok(groupActionOrder.every((position, index) => position >= 0 && (index === 0 || position > groupActionOrder[index - 1])), '그룹 버튼 순서는 작업자 추가→그룹 복제→삭제→접기여야 합니다.');
assert.match(page, /filter\(group => !group\.collapsed\)/, '접힌 Group의 작업자는 우측 선택 계산결과에서 제외해야 합니다.');
assert.match(page, /if \(!group\.collapsed\) groupGridRegistry\.refresh\(group\.client_key\);\s*renderWorkerResult\(\);/, 'Group 접기·펼치기 즉시 우측 계산결과를 갱신해야 합니다.');
assert.doesNotMatch(page, /조건 복사|작업자 복사|단가 포함 복사/);
assert.match(page, /items: group\.items\.map\(copyWorker\)/);
assert.match(page, /workdays: new Map/);
assert.match(grid, /copy\.title = '작업자 복사'/);
assert.match(grid, /duplicateFocused\(key\)/);
assert.match(grid, /group\.items\.splice\(index\s*\+\s*1,\s*0,\s*copiedItem\)/);
assert.match(grid, /group\.items\.splice\(index,\s*1\)/);

console.log('PASS: 그룹과 작업자는 현재 입력내용을 새 client_key로 그대로 복제합니다.');
