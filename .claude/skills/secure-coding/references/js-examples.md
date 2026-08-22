# JavaScript / TypeScript — Secure vs Insecure Examples

## 1. SQL Injection

### ❌ Insecure
```js
const query = `SELECT * FROM users WHERE email = '${req.body.email}'`;
const user = await db.query(query);
```

### ✅ Secure
```js
// Parameterized query — user input never touches the SQL string
const [rows] = await db.execute('SELECT * FROM users WHERE email = ?', [req.body.email]);
```

---

## 2. XSS (Cross-Site Scripting)

### ❌ Insecure
```js
document.getElementById('output').innerHTML = userInput; // Executes scripts in input
```

### ✅ Secure
```js
// textContent never parses HTML — safe for plain text
document.getElementById('output').textContent = userInput;

// If HTML rendering is needed, sanitize first
import DOMPurify from 'dompurify';
element.innerHTML = DOMPurify.sanitize(userInput);
```

---

## 3. Insecure JWT Handling

### ❌ Insecure
```js
const decoded = jwt.decode(token); // No signature verification!
```

### ✅ Secure
```js
// Always verify — check algorithm, expiry, and issuer
const decoded = jwt.verify(token, process.env.JWT_SECRET, {
  algorithms: ['HS256'],  // Explicit algorithm prevents "alg: none" attacks
  issuer: 'your-app',
  audience: 'your-users',
});
```

---

## 4. Storing Secrets

### ❌ Insecure
```js
const API_KEY = 'sk-abc123hardcoded'; // Committed to source control
localStorage.setItem('token', jwtToken); // Accessible to XSS
```

### ✅ Secure
```js
const API_KEY = process.env.API_KEY; // From environment variable

// Store JWT in httpOnly cookie (not accessible to JS)
res.cookie('token', jwtToken, {
  httpOnly: true,
  secure: true,       // HTTPS only
  sameSite: 'strict', // CSRF protection
  maxAge: 3600000,
});
```

---

## 5. CSRF Protection (Express)

### ❌ Insecure
```js
app.post('/transfer', (req, res) => {
  // No origin check — any site can trigger this
  transfer(req.body.amount, req.body.to);
});
```

### ✅ Secure
```js
import csrf from 'csurf';
const csrfProtection = csrf({ cookie: { httpOnly: true, secure: true } });

app.post('/transfer', csrfProtection, (req, res) => {
  transfer(req.body.amount, req.body.to);
});
```

---

## 6. File Upload Validation

### ❌ Insecure
```js
app.post('/upload', upload.single('file'), (req, res) => {
  // Saves any file type — could upload .js, .php, executable
  fs.renameSync(req.file.path, `/uploads/${req.file.originalname}`);
});
```

### ✅ Secure
```js
const ALLOWED_TYPES = ['image/jpeg', 'image/png', 'image/webp'];
const MAX_SIZE = 5 * 1024 * 1024; // 5MB

app.post('/upload', upload.single('file'), (req, res) => {
  if (!ALLOWED_TYPES.includes(req.file.mimetype)) {
    return res.status(400).json({ error: 'File type not allowed' });
  }
  if (req.file.size > MAX_SIZE) {
    return res.status(400).json({ error: 'File too large' });
  }
  // Use a random name — never trust originalname
  const safeName = `${crypto.randomUUID()}${path.extname(req.file.originalname)}`;
  fs.renameSync(req.file.path, `/uploads/${safeName}`);
});
```

---

## 7. Rate Limiting (Express)

### ✅ Secure
```js
import rateLimit from 'express-rate-limit';

const loginLimiter = rateLimit({
  windowMs: 15 * 60 * 1000, // 15 minutes
  max: 10,                   // 10 attempts per window
  message: 'Too many login attempts, please try again later',
});

app.post('/login', loginLimiter, handleLogin);
```

---

## 8. Security Headers (Helmet)

### ✅ Secure
```js
import helmet from 'helmet';

app.use(helmet()); // Sets CSP, HSTS, X-Frame-Options, and more automatically

// Or manually for fine-grained control:
app.use(helmet.contentSecurityPolicy({
  directives: {
    defaultSrc: ["'self'"],
    scriptSrc: ["'self'"],
    styleSrc: ["'self'", "'unsafe-inline'"],
  }
}));
```
