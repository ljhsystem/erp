# 인사·노무관리 메뉴 구조

## 현재 순서

1. 근로계약관리
2. 인사발령관리
3. 직무·배치관리
4. 근태관리
5. 휴가관리
6. 자격·교육관리
7. 성과평가관리
8. 보상·인센티브관리
9. 취업규칙·인사규정

## 자격·교육관리 범위

- SSOT 도메인명: `qualification-education`
- URL: `/institution/human-resources/qualification-education`
- Route key: `web.institution.human_resources.qualification_education`
- 화면: `InstitutionController@webPlaceholder`와 기관업무 공용 Placeholder View를 재사용한다.
- 권한: 공용 WEB Route의 `view` 권한을 사용한다.
- 아이콘: Bootstrap Icons의 `bi-mortarboard`를 사용한다.
- 사이트맵은 WEB Route 메타데이터에서 자동 생성되고 Sidebar 검색은 렌더링된 링크를 대상으로 하므로 별도 검색 인덱스를 만들지 않는다.
- 현재 운영의 `system_page_registry`와 `system_menu_registry`에는 인사·노무 하위 페이지가 등록되어 있지 않고 Sidebar가 정적 Route 구조를 사용하므로 이번 카테고리 추가를 위한 DB 변경은 하지 않는다.

## 제외 범위

- 자격증관리
- 교육관리
- 교육이력
- 자격증이력
- 법정교육
- 관련 테이블·코드그룹·API
