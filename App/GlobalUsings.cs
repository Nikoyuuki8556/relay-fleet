// UseWindowsForms(トレイアイコン用)を有効にしている影響で、SDKが自動生成する暗黙usings
// (obj/.../RelayFleetTracker.GlobalUsings.g.cs) には System.IO / System.Net.Http が含まれない。
// ファイルごとに using を足していくと同じ理由で何度も漏れるため、ここでプロジェクト全体に効かせる。
global using System.IO;
global using System.Net.Http;
