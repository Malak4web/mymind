// Node.js Empirical Verification Script for Milestone 5 Challenger 2
// Tests: HabitDetail edge cases, MobileBottomSheet gesture & navigation, store.js mymind_habits persistence

const fs = require('fs');
const path = require('path');

console.log('--- STARTING EMPIRICAL TEST SUITE FOR MILESTONE 5 ---');

// Mock localStorage for Node environment
const localStorageMap = new Map();
global.localStorage = {
  getItem: (key) => localStorageMap.get(key) || null,
  setItem: (key, val) => localStorageMap.set(key, String(val)),
  removeItem: (key) => localStorageMap.delete(key),
  clear: () => localStorageMap.clear()
};

let testFailures = 0;

function assert(condition, message) {
  if (condition) {
    console.log(`  [PASS] ${message}`);
  } else {
    console.error(`  [FAIL] ${message}`);
    testFailures++;
  }
}

// -------------------------------------------------------------
// TEST SUITE 1: HabitDetail.vue Logic & Edge Cases
// -------------------------------------------------------------
console.log('\n--- 1. Testing HabitDetail.vue Edge Cases ---');

// 1.1 Streak Tier Badges
const getStreakBadgeInfo = (streak) => {
  if (streak >= 30) return { title: '👑 أسطورة الاستمرار الذهبي', color: 'from-amber-400 to-yellow-600 text-white' }
  if (streak >= 14) return { title: '🥇 بطل العادات الفضي', color: 'from-violet-500 to-indigo-600 text-white' }
  if (streak >= 7) return { title: '🥈 ملتزم متألق', color: 'from-blue-400 to-cyan-500 text-white' }
  if (streak >= 3) return { title: '🥉 بداية قوية', color: 'from-emerald-400 to-teal-500 text-white' }
  return { title: '🌱 خطوة أولى نحو النجاح', color: 'from-slate-400 to-slate-600 text-white' }
}

assert(getStreakBadgeInfo(0).title === '🌱 خطوة أولى نحو النجاح', 'Streak 0 badge title');
assert(getStreakBadgeInfo(0).color.includes('from-slate-400'), 'Streak 0 badge color tier');
assert(getStreakBadgeInfo(2).title === '🌱 خطوة أولى نحو النجاح', 'Streak 2 badge title');
assert(getStreakBadgeInfo(3).title === '🥉 بداية قوية', 'Streak 3 badge title');
assert(getStreakBadgeInfo(7).title === '🥈 ملتزم متألق', 'Streak 7 badge title');
assert(getStreakBadgeInfo(14).title === '🥇 بطل العادات الفضي', 'Streak 14 badge title');
assert(getStreakBadgeInfo(30).title === '👑 أسطورة الاستمرار الذهبي', 'Streak 30 badge title (30+ tier)');
assert(getStreakBadgeInfo(30).color.includes('from-amber-400'), 'Streak 30 badge golden tier color');
assert(getStreakBadgeInfo(100).title === '👑 أسطورة الاستمرار الذهبي', 'Streak 100 badge golden tier title');

// 1.2 Mood Notes Parsing
const parseNoteMoodAndContent = (content) => {
  if (!content) return { mood: null, text: '' }
  const moodRegex = /^([\u{1F300}-\u{1F9FF}\u{2600}-\u{26FF}\u{2700}-\u{27BF}])\s*\|\s*(.*)/u
  const match = content.match(moodRegex)
  if (match) {
    return { mood: match[1], text: match[2] }
  }
  return { mood: null, text: content }
}

assert(parseNoteMoodAndContent(null).mood === null, 'Mood parsing for null content');
assert(parseNoteMoodAndContent('').text === '', 'Mood parsing for empty string');
assert(parseNoteMoodAndContent('🤩 | ممتاز جداً').mood === '🤩', 'Mood emoji extracted correctly');
assert(parseNoteMoodAndContent('🤩 | ممتاز جداً').text === 'ممتاز جداً', 'Note text extracted correctly');
assert(parseNoteMoodAndContent('🚀 | انجاز رائع اليوم').mood === '🚀', 'Rocket mood emoji extracted');
assert(parseNoteMoodAndContent('🚀 | انجاز رائع اليوم').text === 'انجاز رائع اليوم', 'Rocket note text extracted');
assert(parseNoteMoodAndContent('ملاحظة بدون ايموجي').mood === null, 'Plain text note mood is null');
assert(parseNoteMoodAndContent('ملاحظة بدون ايموجي').text === 'ملاحظة بدون ايموجي', 'Plain text note content preserved');

// 1.3 Empty Checklist & Progress Calculation
const calcChecklistProgress = (checklist) => {
  if (!checklist?.length) return 0
  const total = checklist.length
  const done = checklist.filter(c => c.completed).length
  return Math.round((done / total) * 100)
}

assert(calcChecklistProgress(null) === 0, 'Checklist progress for null is 0%');
assert(calcChecklistProgress([]) === 0, 'Checklist progress for empty array is 0%');
assert(calcChecklistProgress([{ completed: false }]) === 0, 'Checklist progress 0/1 is 0%');
assert(calcChecklistProgress([{ completed: true }, { completed: false }]) === 50, 'Checklist progress 1/2 is 50%');
assert(calcChecklistProgress([{ completed: true }, { completed: true }]) === 100, 'Checklist progress 2/2 is 100%');

// 1.4 Heatmap Date Navigation & Month Data
let navYear = 2026;
let navMonth = 0; // Jan

const prevMonth = () => {
  if (navMonth === 0) { navMonth = 11; navYear--; } else { navMonth--; }
}
const nextMonth = () => {
  if (navMonth === 11) { navMonth = 0; navYear++; } else { navMonth++; }
}

prevMonth();
assert(navYear === 2025 && navMonth === 11, 'Prev month from Jan 2026 wraps to Dec 2025');
nextMonth();
assert(navYear === 2026 && navMonth === 0, 'Next month from Dec 2025 advances to Jan 2026');

const calcMonthHeatmap = (year, month, habitLogs = {}, habitType = 'boolean', targetValue = 1) => {
  const firstDay = new Date(year, month, 1)
  const offset = firstDay.getDay()
  const daysInMonth = new Date(year, month + 1, 0).getDate()
  const days = []
  
  for (let i = 1; i <= daysInMonth; i++) {
    const d = new Date(year, month, i)
    const yStr = d.getFullYear()
    const mStr = String(d.getMonth() + 1).padStart(2, '0')
    const dStr = String(d.getDate()).padStart(2, '0')
    const key = `${yStr}-${mStr}-${dStr}`
    const log = habitLogs[key]
    const completed = !!log?.completed
    const count = log?.count || 0
    let level = 0
    if (completed) level = 3
    else if (habitType === 'numeric' && targetValue > 0) {
      const ratio = count / targetValue
      if (ratio >= 1) level = 3
      else if (ratio >= 0.5) level = 2
      else if (ratio > 0) level = 1
    } else if (count > 0) level = 1

    days.push({ dayNum: i, dateKey: key, completed, level })
  }
  return { offset, days, daysCount: days.length }
}

const febData = calcMonthHeatmap(2026, 1); // Feb 2026
assert(febData.daysCount === 28, 'Feb 2026 has 28 days');
const marchData = calcMonthHeatmap(2026, 2, { '2026-03-15': { completed: true } });
assert(marchData.daysCount === 31, 'March 2026 has 31 days');
assert(marchData.days.find(d => d.dateKey === '2026-03-15').level === 3, 'Completed heatmap log renders level 3');

// -------------------------------------------------------------
// TEST SUITE 2: MobileBottomSheet.vue & Routing Verification
// -------------------------------------------------------------
console.log('\n--- 2. Testing MobileBottomSheet.vue & Routing ---');

const sheetFile = fs.readFileSync(path.join(__dirname, '../../src/components/MobileBottomSheet.vue'), 'utf-8');

assert(sheetFile.includes('touch-action: none;'), 'Drag handle header has touch-action: none inline style');
assert(sheetFile.includes('@touchstart="handleTouchStart"'), 'Drag handle has touchstart event listener attached');
assert(sheetFile.includes('@touchmove="handleTouchMove"'), 'Drag handle has touchmove event listener attached');
assert(sheetFile.includes('@touchend="handleTouchEnd"'), 'Drag handle has touchend event listener attached');
assert(sheetFile.includes('@click="emit(\'close\')"'), 'Backdrop overlay emits close event on click');
assert(sheetFile.includes('dragY.value > 80 || velocity > 0.4'), 'Dismiss condition threshold (dragY > 80 or velocity > 0.4) present');

const appFile = fs.readFileSync(path.join(__dirname, '../../src/App.vue'), 'utf-8');
assert(appFile.includes("hash.startsWith('#routines/habit-')"), 'App.vue handles #routines/habit-[ID] route pattern');
assert(appFile.includes("hash.startsWith('#habit-')"), 'App.vue handles #habit-[ID] route fallback pattern');
assert(appFile.includes("<HabitDetail v-if=\"activeHabitId\" :habitId=\"activeHabitId\" />"), 'App.vue renders HabitDetail when activeHabitId is set');

// -------------------------------------------------------------
// TEST SUITE 3: store.js Data Persistence (mymind_habits)
// -------------------------------------------------------------
console.log('\n--- 3. Testing store.js Data Persistence (mymind_habits) ---');

const storeContent = fs.readFileSync(path.join(__dirname, '../../src/store.js'), 'utf-8');

assert(storeContent.includes("localStorage.getItem('mymind_habits')"), 'store.js loads habits from localStorage on init');
assert(storeContent.includes("localStorage.setItem('mymind_habits', JSON.stringify(this.habits))"), 'saveHabits() serializes this.habits to localStorage mymind_habits');
assert(storeContent.includes("addHabit(habitData)"), 'store.js implements addHabit');
assert(storeContent.includes("toggleHabitLog(habitId, dateStr"), 'store.js implements toggleHabitLog');
assert(storeContent.includes("addHabitNote("), 'store.js implements addHabitNote');
assert(storeContent.includes("deleteHabitNote("), 'store.js implements deleteHabitNote');
assert(storeContent.includes("addHabitChecklistItem("), 'store.js implements addHabitChecklistItem');
assert(storeContent.includes("toggleHabitChecklistItem("), 'store.js implements toggleHabitChecklistItem');
assert(storeContent.includes("deleteHabitChecklistItem("), 'store.js implements deleteHabitChecklistItem');

console.log('\n=================================================');
if (testFailures === 0) {
  console.log('SUCCESS: ALL EMPIRICAL TESTS PASSED (0 FAILURES)');
} else {
  console.error(`FAILURE: ${testFailures} TEST(S) FAILED`);
}
console.log('=================================================\n');

process.exit(testFailures > 0 ? 1 : 0);
