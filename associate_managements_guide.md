# Guía Completa: Método `associateManagementsToPayment()`

## Descripción General

El método `associateManagementsToPayment()` procesa todos los pagos de una fecha específica y les asigna información sobre las gestiones de cobranza asociadas. Su objetivo es determinar si un pago fue resultado de gestiones de cobranza y calcular los días de mora al momento del pago según cada gestión registrada.

## Flujo General

```
1. Recibe una fecha (ej: '2025-01-15')
2. Obtiene todos los pagos de esa fecha
3. Para cada pago:
   ├─ Busca gestiones efectivas antes del pago
   ├─ Identifica management_auto (más reciente) y management_prev (segunda)
   ├─ Calcula días de mora AL MOMENTO DEL PAGO según cada gestión
   ├─ Determina with_management y post_management
   └─ Actualiza el registro del pago
```

---

## Conceptos Clave

### Campos de la tabla `collection_payments`:

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `with_management` | SI/NO | Indica si hubo gestiones ANTES del pago |
| `management_auto` | ID | ID de la gestión efectiva más reciente antes del pago |
| `days_past_due_auto` | INT | Días de mora AL MOMENTO DEL PAGO según management_auto |
| `management_prev` | ID | ID de la segunda gestión más reciente antes del pago |
| `days_past_due_prev` | INT | Días de mora AL MOMENTO DEL PAGO según management_prev |
| `post_management` | SI/NO | Indica si hubo gestiones DESPUÉS del pago (cuando no hay gestiones antes) |

---

## Paso 1: Obtener Pagos de la Fecha

```php
$payments = \App\Models\CollectionPayment::whereDate('payment_date', $date)->get();
```

### Ejemplo:
**Input:** `$date = '2025-01-15'`

**Output:** Collection con 3 pagos
```php
[
    Payment #1: {
        id: 1001,
        credit_id: 100,
        payment_date: '2025-01-15 10:30:00',
        payment_value: 500.00
    },
    Payment #2: {
        id: 1002,
        credit_id: 101,
        payment_date: '2025-01-15 14:20:00',
        payment_value: 300.00
    },
    Payment #3: {
        id: 1003,
        credit_id: 102,
        payment_date: '2025-01-15 16:45:00',
        payment_value: 750.00
    }
]
```

---

## Paso 2: Iterar sobre cada Pago

```php
foreach ($payments as $payment) {
    $credit_id = $payment->credit_id;
    $payment_date = $payment->payment_date;
    $payment_date_only = \Carbon\Carbon::parse($payment_date)->format('Y-m-d');
```

### Ejemplo (Payment #1):
```php
$credit_id = 100
$payment_date = '2025-01-15 10:30:00'
$payment_date_only = '2025-01-15'
```

---

## Paso 3: Obtener Gestiones Efectivas

```php
$managements = $this->getEffectiveManagements($credit_id, $payment_date);
```

Este método busca todas las gestiones ANTES del pago (con ajuste de 5 horas) que tengan substates "efectivos".

### ¿Por qué 5 horas de ajuste?

```php
// Pago registrado: 2025-01-15 10:30:00
// Fecha ajustada: 2025-01-15 15:30:00 (+ 5 horas)
$adjustedPaymentDate = \Carbon\Carbon::parse($payment_date)->addHours(5)->format('Y-m-d H:i:s');
```

**Razón:** Las gestiones tienen un desfase de +5 horas en su `created_at` respecto a la fecha real. Este ajuste compensa ese desfase.

---

### Substates Efectivos:

Solo se consideran gestiones con estos substates:
```php
[
    'CLIENTE SE NIEGA A PAGAR',
    'CLIENTE INDICA QUE NO ES SU DEUDA',
    'COMPROMISO DE PAGO',
    'CONVENIO DE PAGO',
    'MENSAJE A TERCEROS',
    'MENSAJE DE TEXTO',
    'MENSAJE EN BUZON DE VOZ',
    'MENSAJE EN BUZÓN DEL CLIENTE',
    'NOTIFICADO',
    'ENTREGADO AVISO DE COBRANZA',
    'PASAR A TRÁMITE LEGAL',
    'REGESTIÓN',
    'YA PAGO',
    'OFERTA DE PAGO',
    'YA PAGÓ',
    'SOLICITA REFINANCIAMIENTO',
    'ABONO A DEUDA'
]
```

---

### Ejemplo de Resultado:

```php
// Pago: 2025-01-15 10:30:00
// Fecha ajustada: 2025-01-15 15:30:00 (+ 5 horas)

$managements = Collection [
    Management #501 {
        id: 501,
        credit_id: 100,
        substate: 'COMPROMISO DE PAGO',
        days_past_due: 10,
        created_at: '2025-01-14 09:00:00'  // ANTES del pago
    },
    Management #502 {
        id: 502,
        credit_id: 100,
        substate: 'YA PAGÓ',
        days_past_due: 5,
        created_at: '2025-01-12 11:00:00'  // ANTES del pago
    },
    Management #503 {
        id: 503,
        credit_id: 100,
        substate: 'NOTIFICADO',
        days_past_due: 2,
        created_at: '2025-01-10 14:00:00'  // ANTES del pago
    }
];
```

**Orden:** Las gestiones están ordenadas por `created_at DESC` (más reciente primero).

---

## Paso 4: Determinar if hay gestiones antes del pago

```php
if ($managements && $managements->count() > 0) {
    // Hay gestiones antes del pago
    $with_management = 'SI';
```

Si hay gestiones efectivas antes del pago, automáticamente `with_management = 'SI'`.

---

## Paso 5: Asignar management_auto y management_prev

```php
// La gestión más reciente es management_auto
$management_auto = $managements->first();

// Si hay más de una gestión, la segunda es management_prev
if ($managements->count() > 1) {
    $management_prev = $managements->get(1);
}
```

### Ejemplo:

```php
$management_auto = Management #501; // La más reciente (2025-01-14)
$management_prev = Management #502; // La segunda más reciente (2025-01-12)
```

---

## Paso 6: Calcular days_past_due_auto

**Este es el cálculo más importante del método.**

```php
// Calcular días de mora en la fecha del pago según management_auto
if ($management_auto->days_past_due !== null) {
    $managementDate = \Carbon\Carbon::parse($management_auto->created_at);
    $paymentDateCarbon = \Carbon\Carbon::parse($payment_date);
    $daysDifference = $managementDate->diffInDays($paymentDateCarbon);
    $days_past_due_auto = $management_auto->days_past_due + $daysDifference;
}
```

### Desglose del Cálculo:

1. **`$management_auto->days_past_due`**: Días de mora que tenía el crédito cuando se hizo la gestión
2. **`$daysDifference`**: Días transcurridos entre la gestión y el pago
3. **`$days_past_due_auto`**: Días de mora proyectados al momento del pago

### Fórmula:

```
Días de mora al pago = Días de mora en gestión + Días transcurridos
```

---

### Ejemplo Detallado:

**Datos:**
- **Management_auto**:
  - Fecha: 2025-01-14 09:00:00
  - days_past_due: 10 (el crédito tenía 10 días de mora ese día)
- **Pago**:
  - Fecha: 2025-01-15 10:30:00

**Cálculo:**
```php
$managementDate = Carbon::parse('2025-01-14 09:00:00');
$paymentDateCarbon = Carbon::parse('2025-01-15 10:30:00');
$daysDifference = 1; // 1 día entre la gestión y el pago

$days_past_due_auto = 10 + 1 = 11;
```

**Resultado:** `$days_past_due_auto = 11`

**Interpretación:**
- La gestión se hizo cuando el crédito tenía 10 días de mora
- Pasó 1 día entre la gestión y el pago
- Por tanto, al momento del pago, el crédito tenía 11 días de mora (según esta gestión)

---

## Paso 7: Calcular days_past_due_prev

El mismo proceso se aplica para `management_prev` (la segunda gestión más reciente).

```php
// Calcular días de mora en la fecha del pago según management_prev
if ($management_prev->days_past_due !== null) {
    $managementPrevDate = \Carbon\Carbon::parse($management_prev->created_at);
    $paymentDateCarbon = \Carbon\Carbon::parse($payment_date);
    $daysDifference = $managementPrevDate->diffInDays($paymentDateCarbon);
    $days_past_due_prev = $management_prev->days_past_due + $daysDifference;
}
```

### Ejemplo:

**Datos:**
- **Management_prev**:
  - Fecha: 2025-01-12 11:00:00
  - days_past_due: 8 (el crédito tenía 8 días de mora ese día)
- **Pago**:
  - Fecha: 2025-01-15 10:30:00

**Cálculo:**
```php
$managementPrevDate = Carbon::parse('2025-01-12 11:00:00');
$paymentDateCarbon = Carbon::parse('2025-01-15 10:30:00');
$daysDifference = 3; // 3 días entre la gestión prev y el pago

$days_past_due_prev = 8 + 3 = 11;
```

**Resultado:** `$days_past_due_prev = 11`

**Interpretación:**
- Esta gestión se hizo cuando el crédito tenía 8 días de mora
- Pasaron 3 días entre esta gestión y el pago
- Por tanto, al momento del pago, el crédito tenía 11 días de mora (según esta gestión)

---

### Nota Importante: Consistencia de Cálculos

**Ambos cálculos deben dar el mismo resultado** (o muy similar), ya que ambos proyectan los días de mora al mismo momento (la fecha del pago).

En el ejemplo:
- `days_past_due_auto = 11`
- `days_past_due_prev = 11`

Esto confirma que el cálculo es correcto.

---

## Paso 8: Caso sin gestiones antes del pago

```php
} else {
    // No hay gestiones antes del pago, verificar si hay después
    $adjustedPaymentDate = \Carbon\Carbon::parse($payment_date)->addHours(5)->format('Y-m-d H:i:s');
    $managementsAfter = \App\Models\Management::where('credit_id', $credit_id)
        ->where('created_at', '>', $adjustedPaymentDate)
        ->whereIn('substate', [...])
        ->exists();

    if ($managementsAfter) {
        $post_management = 'SI';
    }
}
```

### Lógica:

- Si **NO** hay gestiones antes del pago
- Se busca si hay gestiones **DESPUÉS** del pago
- Si existen gestiones después: `post_management = 'SI'`
- Si no existen gestiones después: `post_management = 'NO'`

---

## Paso 9: Actualizar el Pago

```php
$payment->update([
    'with_management' => $with_management,
    'management_auto' => $management_auto ? $management_auto->id : null,
    'days_past_due_auto' => $days_past_due_auto,
    'management_prev' => $management_prev ? $management_prev->id : null,
    'days_past_due_prev' => $days_past_due_prev,
    'post_management' => $post_management
]);
```

---

### Ejemplo de Actualización Completa:

**Antes:**
```php
CollectionPayment #1001 {
    id: 1001,
    credit_id: 100,
    payment_date: '2025-01-15 10:30:00',
    payment_value: 500.00,
    with_management: null,
    management_auto: null,
    days_past_due_auto: null,
    management_prev: null,
    days_past_due_prev: null,
    post_management: null
}
```

**Después:**
```php
CollectionPayment #1001 {
    id: 1001,
    credit_id: 100,
    payment_date: '2025-01-15 10:30:00',
    payment_value: 500.00,
    with_management: 'SI',
    management_auto: 501,
    days_past_due_auto: 11,
    management_prev: 502,
    days_past_due_prev: 11,
    post_management: 'NO'
}
```

---

## Ejemplo Completo con tu Caso Específico

### Datos Iniciales:

```php
// Gestión management_auto
Management #501 {
    id: 501,
    credit_id: 100,
    substate: 'COMPROMISO DE PAGO',
    days_past_due: 10,
    created_at: '2025-12-02 09:00:00'
}

// Pago
Payment #1001 {
    id: 1001,
    credit_id: 100,
    payment_date: '2025-12-20 14:30:00',
    payment_value: 500.00
}
```

---

### Procesamiento:

**1. Buscar gestiones antes del pago:**
```php
// Pago: 2025-12-20 14:30:00
// Ajustado: 2025-12-20 19:30:00 (+ 5 horas)
// Encuentra Management #501 (2025-12-02 09:00:00)

$managements = [Management #501];
$with_management = 'SI';
```

**2. Asignar management_auto:**
```php
$management_auto = Management #501;
```

**3. Calcular days_past_due_auto:**
```php
$managementDate = Carbon::parse('2025-12-02 09:00:00');
$paymentDateCarbon = Carbon::parse('2025-12-20 14:30:00');
$daysDifference = 18; // 18 días entre gestión y pago

$days_past_due_auto = 10 + 18 = 28;
```

**4. Resultado:**
- El crédito tenía **10 días de mora** cuando se hizo la gestión (2 dic)
- Pasaron **18 días** entre la gestión y el pago
- Por tanto, al momento del pago (20 dic), el crédito tenía **28 días de mora**

---

### Si hubiera una gestión anterior (management_prev):

```php
// Gestión management_prev
Management #500 {
    id: 500,
    credit_id: 100,
    substate: 'NOTIFICADO',
    days_past_due: 3,
    created_at: '2025-11-25 11:00:00'
}
```

**Cálculo:**
```php
$managementPrevDate = Carbon::parse('2025-11-25 11:00:00');
$paymentDateCarbon = Carbon::parse('2025-12-20 14:30:00');
$daysDifference = 25; // 25 días entre gestión prev y pago

$days_past_due_prev = 3 + 25 = 28;
```

**Resultado:**
- El crédito tenía **3 días de mora** cuando se hizo esta gestión (25 nov)
- Pasaron **25 días** entre esta gestión y el pago
- Por tanto, al momento del pago (20 dic), el crédito tenía **28 días de mora**

**Nota:** Ambas gestiones proyectan **28 días de mora** al momento del pago, lo cual es correcto.

---

## Interpretación de Flags

### Tabla de Escenarios:

| with_management | post_management | management_auto | management_prev | Interpretación |
|-----------------|-----------------|-----------------|-----------------|----------------|
| SI | NO | 501 | 502 | Pago con gestiones previas (probablemente efectivas) |
| SI | NO | 501 | null | Pago con una gestión previa |
| NO | SI | null | null | Pago espontáneo, pero con seguimiento posterior |
| NO | NO | null | null | Pago completamente espontáneo sin gestiones |

---

## Casos de Uso

### Caso 1: Cliente Respondió a Gestiones

```php
// Resultado:
with_management: 'SI'
management_auto: 501
days_past_due_auto: 28
management_prev: 500
days_past_due_prev: 28
post_management: 'NO'
```

**Análisis:** Este cliente pagó después de recibir gestiones de cobranza. Las gestiones fueron efectivas. El crédito tenía 28 días de mora al momento del pago.

---

### Caso 2: Cliente Pagó Espontáneamente

```php
// Resultado:
with_management: 'NO'
management_auto: null
days_past_due_auto: null
management_prev: null
days_past_due_prev: null
post_management: 'NO'
```

**Análisis:** Este cliente pagó sin necesidad de gestiones de cobranza. Es un buen pagador que no requiere presión.

---

### Caso 3: Cliente Pagó y Luego Fue Contactado

```php
// Resultado:
with_management: 'NO'
management_auto: null
days_past_due_auto: null
management_prev: null
days_past_due_prev: null
post_management: 'SI'
```

**Análisis:** El cliente pagó espontáneamente, pero después se realizaron gestiones (posiblemente para confirmar el pago o para otros créditos).

---

## Diferencias Conceptuales Importantes

### ❌ Concepto INCORRECTO (anterior):

> "Calcular los días de mora que tenía el crédito al momento de la gestión"

Esto llevaba a calcular:
```php
// INCORRECTO
$days_past_due_auto = 10; // días en la gestión
```

### ✅ Concepto CORRECTO (actual):

> "Calcular los días de mora que tenía el crédito al momento del PAGO, según la información de la gestión"

Esto lleva a calcular:
```php
// CORRECTO
$days_past_due_auto = 10 + 18 = 28; // días al momento del pago
```

---

## Ventajas del Método Actual

1. **Consistencia**: Ambos valores (`days_past_due_auto` y `days_past_due_prev`) proyectan al mismo momento (fecha del pago), por lo que deben ser iguales o muy similares.

2. **Simplicidad**: No necesita validar si el crédito estuvo activo o no en la fecha del pago. Usa directamente el campo `days_past_due` que ya tiene cada gestión.

3. **Precisión**: Cada gestión registra los días de mora en su momento, y el método simplemente proyecta ese valor hacia adelante.

---

## Propósito del Método

Este método permite responder preguntas importantes para el análisis de cobranza:

1. **¿Qué pagos fueron resultado de gestiones de cobranza?**
   - Respuesta: Los que tienen `with_management = 'SI'`

2. **¿Cuántos días de mora tenía el crédito al momento del pago?**
   - Respuesta: `days_past_due_auto` (o `days_past_due_prev`, ambos deben coincidir)

3. **¿Qué gestiones específicas influyeron en el pago?**
   - Respuesta: `management_auto` y `management_prev` (IDs)

4. **¿Qué pagos fueron espontáneos?**
   - Respuesta: Los que tienen `with_management = 'NO'` y `post_management = 'NO'`

5. **¿Hubo seguimiento después del pago?**
   - Respuesta: Los que tienen `post_management = 'SI'`

---

## Diagrama de Flujo Simplificado

```
START
  ↓
Obtener pagos de la fecha
  ↓
Para cada pago:
  ↓
  Buscar gestiones antes del pago (con +5h)
  ↓
  ¿Hay gestiones?
    ├─ SÍ → Asignar management_auto y management_prev
    │        Calcular days_past_due_auto:
    │          = days_past_due (gestión) + días transcurridos
    │        Calcular days_past_due_prev:
    │          = days_past_due (gestión) + días transcurridos
    │        with_management = 'SI'
    └─ NO → Buscar gestiones después
             ├─ SÍ → post_management = 'SI'
             └─ NO → post_management = 'NO'
  ↓
  Actualizar registro del pago
  ↓
Siguiente pago
  ↓
END
```

---

## Notas Técnicas

### Función `diffInDays()`

```php
$date1 = Carbon::parse('2025-12-02');
$date2 = Carbon::parse('2025-12-20');
$diff = $date1->diffInDays($date2); // 18 días
```

**Importante:** `diffInDays()` siempre retorna un valor absoluto (positivo).

---

### Campo `days_past_due` en Gestiones

Cada gestión tiene un campo `days_past_due` que indica cuántos días de mora tenía el crédito al momento de crear esa gestión. Este valor es fundamental para el cálculo.

**Ejemplo:**
```php
Management {
    id: 501,
    credit_id: 100,
    days_past_due: 10,  // ← El crédito tenía 10 días de mora
    created_at: '2025-12-02 09:00:00'
}
```

---

### whereIn con Substates

```php
->whereIn('substate', [
    'COMPROMISO DE PAGO',
    'YA PAGÓ',
    // ... etc
])
```

**Propósito:** Filtrar solo las gestiones que son "efectivas" para análisis de cobranza. No todas las gestiones son relevantes para determinar si influyeron en un pago.

---

## Fin de la Guía