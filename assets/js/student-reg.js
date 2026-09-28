/**
 * KIUT student registration number helpers
 * Format: COURSE/NUMBER/YEAR/DT  e.g. BCS/23456/2301/DT
 */
(function (global) {
  const COURSE_CODES = [
    'LLB', 'BCS', 'BIT', 'BBA', 'BMS', 'BMLS', 'DCM', 'CIT', 'DBA',
    'BCO', 'BPH', 'BCM', 'BPL', 'BNS', 'BED', 'MED', 'PHAR', 'NUR', 'LAW'
  ];

  const INTAKE_YEARS = ['2301', '2401', '2501', '2601'];

  const FACULTIES = {
    'Computing, Management and Social Sciences': [
      'Computer Science', 'Information Technology', 'Business Administration',
      'Economics', 'Psychology', 'Law'
    ],
    'Allied Health Sciences': [
      'Nursing', 'Medical Laboratory', 'Pharmacy', 'Physiotherapy', 'Public Health'
    ],
    'Education and Legal Studies': [
      'Education', 'Law', 'Early Childhood', 'Special Education', 'Legal Studies'
    ],
    'Medicine and Pharmaceutical Sciences': [
      'Medicine', 'Surgery', 'Pharmacy', 'Dentistry', 'Biomedical Sciences'
    ]
  };

  const COURSE_DEPARTMENT = {
    BCS: 'Computer Science',
    BIT: 'Information Technology',
    CIT: 'Information Technology',
    BCO: 'Computer Science',
    BBA: 'Business Administration',
    DBA: 'Business Administration',
    DCM: 'Business Administration',
    BCM: 'Business Administration',
    BMS: 'Biomedical Sciences',
    BMLS: 'Medical Laboratory',
    BPH: 'Pharmacy',
    PHAR: 'Pharmacy',
    NUR: 'Nursing',
    LLB: 'Law',
    LAW: 'Law',
    BED: 'Education',
    MED: 'Medicine',
    BPL: 'Pharmacy',
    BNS: 'Nursing'
  };

  function normalizeRegNo(value) {
    return String(value || '').trim().replace(/\s+/g, '').toUpperCase();
  }

  function parseRegNo(value) {
    const regNo = normalizeRegNo(value);
    const match = regNo.match(/^([A-Z]{2,5})\/(\d+)\/(2[3-6]01)\/(DT)$/);
    if (!match) return null;
    return {
      regNo,
      course: match[1],
      number: match[2],
      year: match[3],
      suffix: match[4]
    };
  }

  function isValidRegNo(value) {
    const parsed = parseRegNo(value);
    if (!parsed) return false;
    if (!COURSE_CODES.includes(parsed.course)) return false;
    if (!INTAKE_YEARS.includes(parsed.year)) return false;
    return true;
  }

  function buildRegNo(course, number, year) {
    return `${String(course).toUpperCase()}/${String(number)}/${String(year)}/DT`;
  }

  function emailFromRegNo(regNo) {
    return normalizeRegNo(regNo).toLowerCase().replace(/\//g, '.') + '@student.kiut.ac.tz';
  }

  function defaultNameFromRegNo(regNo) {
    return 'Student (' + normalizeRegNo(regNo) + ')';
  }

  function departmentFromCourse(course, fallback) {
    return COURSE_DEPARTMENT[String(course || '').toUpperCase()] || fallback || 'General Studies';
  }

  function buildRangeRows(course, fromNum, toNum, year, faculty, department) {
    const courseUp = String(course || '').toUpperCase();
    const dept = department || departmentFromCourse(courseUp);
    const rows = [];
    const from = parseInt(fromNum, 10);
    const to = parseInt(toNum, 10);
    if (!courseUp || Number.isNaN(from) || Number.isNaN(to) || from > to) return { rows: [], error: 'Invalid range' };
    if (to - from > 500) return { rows: [], error: 'Maximum 500 students per range' };
    if (!INTAKE_YEARS.includes(String(year))) return { rows: [], error: 'Invalid intake year (use 2301, 2401, 2501, 2601)' };
    if (!COURSE_CODES.includes(courseUp)) return { rows: [], error: 'Invalid course code' };

    for (let n = from; n <= to; n++) {
      const regNo = buildRegNo(courseUp, n, year);
      rows.push({
        reg_no: regNo,
        name: defaultNameFromRegNo(regNo),
        email: emailFromRegNo(regNo),
        faculty: faculty || '',
        department: dept
      });
    }
    return { rows, error: null };
  }

  function pickField(row, keys) {
    const normalized = {};
    Object.keys(row || {}).forEach((k) => {
      normalized[String(k).trim().toLowerCase().replace(/[\s_-]+/g, '')] = row[k];
    });
    for (const key of keys) {
      const val = normalized[key.replace(/[\s_-]+/g, '')];
      if (val !== undefined && val !== null && String(val).trim() !== '') return String(val).trim();
    }
    return '';
  }

  function normalizeImportRow(row, defaults) {
    const regRaw = pickField(row, ['regno', 'registrationnumber', 'regnumber', 'idnumber', 'studentid']);
    const regNo = normalizeRegNo(regRaw);
    const parsed = parseRegNo(regNo);
    const faculty = pickField(row, ['faculty']) || defaults?.faculty || '';
    let department = pickField(row, ['department', 'dept']) || defaults?.department || '';
    if (!department && parsed) department = departmentFromCourse(parsed.course, defaults?.department);
    const name = pickField(row, ['name', 'fullname', 'studentname']) || defaultNameFromRegNo(regNo);
    const email = pickField(row, ['email', 'emailaddress']) || emailFromRegNo(regNo);

    return {
      reg_no: regNo,
      name,
      email: email.toLowerCase(),
      faculty,
      department,
      valid: isValidRegNo(regNo),
      error: isValidRegNo(regNo) ? '' : 'Invalid format. Use e.g. BCS/23456/2301/DT'
    };
  }

  function normalizeImportRows(rows, defaults) {
    return (rows || []).map((row) => normalizeImportRow(row, defaults)).filter((r) => r.reg_no);
  }

  function parseCsvText(text, defaults) {
    const lines = String(text || '').split(/\r?\n/).filter((l) => l.trim());
    if (lines.length < 2) return [];
    const headers = lines[0].split(',').map((h) => h.trim().replace(/^"|"$/g, ''));
    const rows = lines.slice(1).map((line) => {
      const cols = line.match(/("([^"]|"")*"|[^,]*)/g) || [];
      const obj = {};
      headers.forEach((h, i) => {
        obj[h] = (cols[i] || '').replace(/^"|"$/g, '').replace(/""/g, '"').trim();
      });
      return obj;
    });
    return normalizeImportRows(rows, defaults);
  }

  async function parseUploadFile(file, defaults) {
    if (!file) throw new Error('No file selected');
    const name = (file?.name || '').toLowerCase();

    if (name.endsWith('.csv')) {
      const text = await file.text();
      if (!text.trim()) throw new Error('The CSV file is empty');
      return parseCsvText(text, defaults);
    }

    if (name.endsWith('.xlsx') || name.endsWith('.xls')) {
      if (typeof XLSX === 'undefined') {
        throw new Error('Excel library failed to load. Refresh the page or upload a CSV file instead.');
      }
      try {
        const buffer = await file.arrayBuffer();
        const wb = XLSX.read(new Uint8Array(buffer), { type: 'array' });
        if (!wb.SheetNames?.length) throw new Error('Excel file has no worksheets');
        const sheet = wb.Sheets[wb.SheetNames[0]];
        const json = XLSX.utils.sheet_to_json(sheet, { defval: '' });
        if (!json.length) throw new Error('Excel sheet is empty or headers are missing');
        return normalizeImportRows(json, defaults);
      } catch (err) {
        if (err.message && !err.message.includes('Excel')) {
          throw new Error('Could not read Excel file: ' + err.message);
        }
        throw err;
      }
    }

    throw new Error('Unsupported file type. Upload .xlsx or .csv');
  }

  function downloadCsvTemplate() {
    const header = 'reg_no,name,email,faculty,department';
    const sample = 'BCS/23456/2301/DT,Amina Juma,,Computing Management and Social Sciences,Computer Science';
    const blob = new Blob([header + '\n' + sample + '\n'], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'student_reg_template.csv';
    a.click();
    URL.revokeObjectURL(url);
  }

  global.StudentReg = {
    COURSE_CODES,
    INTAKE_YEARS,
    FACULTIES,
    normalizeRegNo,
    parseRegNo,
    isValidRegNo,
    buildRegNo,
    emailFromRegNo,
    defaultNameFromRegNo,
    departmentFromCourse,
    buildRangeRows,
    normalizeImportRows,
    parseCsvText,
    parseUploadFile,
    downloadCsvTemplate
  };
})(window);
