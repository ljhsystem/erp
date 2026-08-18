# HTML Grid Architecture

## 1. 아키텍처

공용 HTML Grid Core 1.0은 입력형 화면을 위한 경량 엔진이다.  
업무 규칙은 Core 내부에 두지 않고, `schema`, `hooks`, `bridge`, `plugin adapter`를 통해 외부에서 주입한다.

핵심 흐름:

`State -> Renderer -> HTML`

상태 변경은 반드시 `Command`를 통해서만 수행하고, 표시/검증/직렬화는 별도 모듈이 담당한다.

## 2. 모듈 구조

- `state.js`: Grid 상태 SSOT 생성과 lifecycle 정규화
- `schema.js`: 입력 schema를 immutable runtime schema로 변환
- `column-manager.js`: 컬럼 order, hidden, pinned, width, meta SSOT 관리
- `event-bus.js`: 내부 모듈 간 이벤트 전달
- `formatter.js`: 표시 전용 formatter registry
- `editor-registry.js`: editor factory registry
- `plugin-registry.js`: plugin lifecycle registry
- `renderer.js`: header/body/footer/empty renderer 오케스트레이션
- `selection.js`: 선택 상태 제어
- `keyboard.js`: 키 이벤트를 API/Command 호출로 변환
- `resize.js`: 컬럼 너비 interaction
- `reorder.js`: 행 reorder interaction
- `validator.js`: 공용 검증 엔진
- `footer.js`: footer 계산 엔진
- `serializer.js`: state 기반 저장 payload 생성
- `commands/*`: 상태 변경 유일 진입점
- `index.js`: Core 조립과 외부 공개 API 제공

## 3. Lifecycle

Grid lifecycle:

1. `createHtmlGrid(config)`
2. schema 생성
3. state 생성
4. column manager 생성
5. event bus 생성
6. formatter/editor/plugin registry 생성
7. validator/footer/serializer 생성
8. renderer 생성
9. selection/keyboard/resize/reorder 생성
10. API 조립
11. `render()`
12. `destroy()`

Plugin lifecycle:

1. `init()`
2. `mount()`
3. `update()`
4. `destroy()`

Editor lifecycle:

1. `create()`
2. `mount()`
3. `focus()`
4. `blur()`
5. `getValue() / setValue()`
6. `validate()`
7. `destroy()`

## 4. State 구조

```js
{
  gridId: '',
  capabilities: {},
  rows: [],
  cells: {},
  columns: {
    order: [],
    hidden: [],
    pinned: [],
    widths: {},
    meta: {},
  },
  selection: {
    activeCell: null,
    range: null,
    selectedRowIds: [],
  },
  ui: {
    loading: false,
    empty: false,
    editing: null,
  },
  validation: {
    hasError: false,
    rowErrors: {},
    cellErrors: {},
    messages: [],
  },
  footer: {
    values: {},
    messages: [],
    hasDifference: false,
  },
  meta: {
    pluginState: {},
    renderVersion: 0,
  },
}
```

## 5. Row Lifecycle

- `created`: 신규 생성 행
- `clean`: 원본과 동일한 행
- `updated`: 값 변경 행
- `deleted`: 삭제 표시 행
- `readonly`: 읽기 전용 행
- `disabled`: 입력 비활성 행
- `locked`: 잠금 행
- `saving`: 저장 중 행
- `error`: 행 단위 오류 상태

## 6. Cell Lifecycle

- `normal`: 기본 상태
- `editing`: 편집 중
- `dirty`: 원본 대비 변경됨
- `invalid`: 검증 실패
- `readonly`: 읽기 전용
- `disabled`: 비활성
- `focused`: 포커스됨
- `selected`: 선택됨

## 7. Capability

Capability는 Grid 기능 자체의 On/Off 스위치다.

예시:

```js
capabilities: {
  addRow: true,
  deleteRow: true,
  insertRow: true,
  reorder: true,
  resize: true,
  keyboard: true,
  clipboard: false,
  footer: true,
  validation: true,
  selection: true,
  multiSelection: false,
  columnHide: true,
  columnMove: true,
  columnResize: true,
  stickyHeader: true,
}
```

## 8. Plugin

Plugin은 외부 UI 라이브러리 연결만 담당한다.  
State, Renderer, Command, Validator, Footer, Serializer를 직접 수정하지 않는다.

기본 plugin:

- `select2`
- `datepicker`
- `time-picker`
- `number`
- `currency`
- `code-picker`
- `account-picker`

## 9. Editor

Editor는 입력만 담당한다.  
기본 editor:

- `text`
- `number`
- `date`
- `select`

Editor는 값을 반환하지만 Grid 상태는 수정하지 않는다.

## 10. Command

모든 상태 변경은 Command를 통해 처리한다.

기본 command:

- `add-row`
- `insert-row`
- `delete-row`
- `move-row`
- `move-cell`
- `update-cell`
- `update-row`

## 11. Render Cycle

전체 렌더:

`render() -> header/body/footer/empty -> width sync -> plugin mount/update`

부분 렌더:

- `row:updated` -> `updateRow`
- `cell:changed` -> `updateCell`
- `row:added`, `row:inserted`, `row:moved` -> `renderBody`
- `footer:changed` -> `renderFooter`
- `column:resized` -> width sync

## 12. Event Flow

예시:

`Keyboard -> API -> Command -> State -> Validator/Footer -> EventBus -> Renderer Partial Update -> Plugin Update`

EventBus는 내부 동기화 전용이며, 업무 저장이나 외부 bridge 호출은 직접 수행하지 않는다.

## 13. API

Core 1.0 공개 API:

- `addRow()`
- `insertRow()`
- `deleteRow()`
- `updateRow()`
- `updateCell()`
- `render()`
- `refresh()`
- `validate()`
- `serialize()` (호출 전에 활성 editor 값을 공용 editor의 `getValue()`로 state에 커밋)
- `commitEditors()` (화면별 DOM 순회 없이 editor 값을 state SSOT에 반영)
- `focusFirstError()` (validation 결과의 첫 오류 셀로 이동)
- `destroy()`
- `on()`
- `off()`
- `execute()`
- `getState()`
- `setState()`
- `getColumnState()`
- `setColumnState()`
- `focusCell()`
- `selectRow()`
- `beginEdit()`
- `endEdit()`
- `resizeColumn()`
- `reorderRow()`

## 14. Bridge

Core 내부는 업무 저장소를 모른다.  
`system_user_settings`, API 저장, 업무 payload 저장은 모두 외부 bridge에서 처리한다.

Core는 다음 값만 외부로 전달한다.

- `state`
- `validation`
- `footer`
- `serialize()` 결과
- `eventBus` 이벤트

## 15. 확장 방법

1. 신규 업무 화면은 DB/업무 규칙을 Core에 넣지 않는다.
2. 화면별로 `schema`, `hooks`, `adapters`, `bridge`만 추가한다.
3. 신규 editor가 필요하면 `editor-registry`에 등록한다.
4. 신규 plugin이 필요하면 `plugin-registry`에 등록한다.
5. 저장 포맷이 다르면 `serializer hooks`로 변환한다.
6. 검증 규칙이 다르면 `validator hooks`로 확장한다.

## 16. 공용 스타일과 근로계약 적용

- 공용 입력형 표의 높이, padding, box-sizing, 단일행 헤더, ellipsis/tooltip, 필수표시, 오류표시는 `public/assets/css/components/html-grid.css`를 기준으로 한다.
- 화면 schema의 `width`가 컬럼 폭 SSOT이며 화면 CSS에서 컬럼별 고정폭을 중복 선언하지 않는다.
- 근로계약 지급조건은 전표입력 분개라인의 editor lifecycle과 행 상태 규칙을 재사용한다. 업무별 마스터 스냅샷과 근로수당 조건부 입력 변환은 근로계약 adapter에만 둔다.
- `validate()`와 `serialize()`는 편집기 표시값을 state에 먼저 커밋하므로 화면별 DOM 직접 직렬화를 두지 않는다.

## 조건부 필수표시

DB NULL 여부에 따른 기본 필수조건은 column.required, 행 상태에 따른 조건부 필수조건은 column.meta.requiredIndicator로 표시한다. 실제 값 검증은 화면 hook과 서버 Validation이 동일 정책을 적용하며, 표시 전용 meta가 값을 강제하지는 않는다.
