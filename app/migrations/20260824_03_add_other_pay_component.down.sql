DELETE component
FROM institution_employment_contracts_pay_components component
WHERE component.id = '0d852729-d6cb-4d4d-9b09-758b2ec3f110'
  AND component.component_code = 'OTHER_PAY'
  AND NOT EXISTS (
    SELECT 1 FROM institution_employment_contracts_components contract_component
    WHERE contract_component.pay_component_id = component.id
  )
  AND NOT EXISTS (
    SELECT 1 FROM institution_regular_employment_income_line_items income_line
    WHERE income_line.source_reference_id = component.id
  );
