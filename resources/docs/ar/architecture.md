# البنية

بنية kory هي مكوّناته والروابط بينها. تُرسم مخططًا على صفحة الوحدة، وهي إلزامية لطلب الاعتماد.

## لماذا تصرّح بها

يفهم القارئ في ثوانٍ كيف بُني kory، قبل أن يقرأ الشيفرة. وتُستخدم المكوّنات كذلك في البحث.

## kory graph

```
kory graph payment
```

تحلّل الأداة ملفات kory، وتعرض المكوّنات والروابط التي وجدتها، ثم تقترح كتابتها في `kory.json`. لا يُنفَّذ أي شيء: التحليل يقرأ الشيفرة فقط.

## اللغات المعروفة

PHP وJavaScript وTypeScript وPython وJava وKotlin وDart وGo. للغة أخرى، اكتب البنية يدويًا في `kory.json`.

## الصيغة

```json
"architecture": {
  "components": [
    { "id": "PaymentService", "file": "src/PaymentService.php", "kind": "class" },
    { "id": "PaymentGateway", "file": "src/Contracts/PaymentGateway.php", "kind": "interface" }
  ],
  "links": [
    { "from": "PaymentService", "to": "PaymentGateway", "kind": "uses" }
  ]
}
```

أنواع المكوّنات: `class` و`interface` و`trait` و`enum` و`function` و`module`. أنواع الروابط: `uses` و`implements` و`extends` و`calls`.

## قراءة المخطط

في صفحة الوحدة، يرسم تبويب «البنية» كل مكوّن حسب نوعه (صنف، واجهة…) وكل رابط حسب نوعه (يستخدم، ينفّذ…).
